<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MouvementStock;
use App\Entity\Sejour;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

final class AuditMouvementStock
{
    public const MODIFICATION = 'MODIFICATION';
    public const ANNULATION = 'ANNULATION';

    private const ACTIONS = [self::MODIFICATION, self::ANNULATION];

    public function __construct(private readonly Connection $connexion)
    {
    }

    /** @return array<string, mixed> */
    public function instantane(MouvementStock|string $mouvement): array
    {
        $id = $mouvement instanceof MouvementStock ? (string) $mouvement->getId() : $mouvement;
        $entete = $this->connexion->fetchAssociative(
            <<<'SQL'
                SELECT m.id, m.sejour_id, m.utilisateur_id, m.groupe_id, m.menu_id,
                       tm.code AS type, o.code AS origine, m.date_mouvement,
                       m.cle_soumission, m.annule_at, m.annule_par_id,
                       m.motif_annulation, m.created_at, m.updated_at
                FROM campement.mouvement_stock m
                JOIN campement.type_mouvement tm ON tm.id = m.type_mouvement_id
                JOIN campement.origine_mouvement o ON o.id = m.origine_mouvement_id
                WHERE m.id = :id
                SQL,
            ['id' => $id],
        );
        if (false === $entete) {
            throw new \LogicException('Le mouvement à auditer est introuvable.');
        }

        $lignes = $this->connexion->fetchAllAssociative(
            <<<'SQL'
                SELECT l.id, l.denree_id, d.nom AS denree,
                       l.reference_fournisseur_id, l.conditionnement_saisie_id,
                       l.quantite_saisie,
                       l.numero_lot
                FROM campement.mouvement_stock_ligne l
                JOIN campement.denree d ON d.id = l.denree_id
                WHERE l.mouvement_stock_id = :id
                ORDER BY d.nom, l.id
                SQL,
            ['id' => $id],
        );
        $details = $this->connexion->fetchAllAssociative(
            <<<'SQL'
                SELECT lc.mouvement_stock_ligne_id, lc.conditionnement_id, lc.quantite
                FROM campement.mouvement_stock_ligne_conditionnement lc
                JOIN campement.mouvement_stock_ligne l ON l.id = lc.mouvement_stock_ligne_id
                WHERE l.mouvement_stock_id = :id
                ORDER BY lc.mouvement_stock_ligne_id, lc.conditionnement_id
                SQL,
            ['id' => $id],
        );
        $detailsParLigne = [];
        foreach ($details as $detail) {
            $detailsParLigne[(string) $detail['mouvement_stock_ligne_id']][] = [
                'conditionnement_id' => $detail['conditionnement_id'],
                'quantite' => $detail['quantite'],
            ];
        }
        foreach ($lignes as &$ligne) {
            $ligne['conditionnements'] = $detailsParLigne[(string) $ligne['id']] ?? [];
        }
        unset($ligne);

        return ['mouvement' => $entete, 'lignes' => $lignes];
    }

    /**
     * @param array<string, mixed>      $avant
     * @param array<string, mixed>|null $apres
     */
    public function enregistrer(
        MouvementStock|string $mouvement,
        Sejour $sejour,
        Utilisateur $utilisateur,
        string $action,
        string $motif,
        array $avant,
        ?array $apres,
    ): void {
        $motif = trim($motif);
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException('Action d’audit inconnue.');
        }
        if ('' === $motif) {
            throw new \InvalidArgumentException('Le motif de l’opération est obligatoire.');
        }
        if (null === $apres) {
            throw new \InvalidArgumentException('L’état après opération est obligatoire.');
        }

        $mouvementId = $mouvement instanceof MouvementStock ? (string) $mouvement->getId() : $mouvement;
        $libelle = trim($utilisateur->getPrenom().' '.$utilisateur->getNom()).' <'.$utilisateur->getEmail().'>';
        $this->connexion->insert('campement.audit_mouvement_stock', [
            'id' => Uuid::v7()->toRfc4122(),
            'mouvement_stock_id' => $mouvementId,
            'sejour_id' => (string) $sejour->getId(),
            'utilisateur_id' => (string) $utilisateur->getId(),
            'utilisateur_libelle' => $libelle,
            'action' => $action,
            'motif' => $motif,
            'etat_avant' => $avant,
            'etat_apres' => $apres,
        ], [
            'etat_avant' => Types::JSON,
            'etat_apres' => Types::JSON,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function historique(Sejour $sejour): array
    {
        return $this->connexion->fetchAllAssociative(
            <<<'SQL'
                SELECT mouvement_stock_id, utilisateur_libelle, action, motif, created_at
                FROM campement.audit_mouvement_stock
                WHERE sejour_id = :sejour
                ORDER BY created_at DESC
                LIMIT 100
                SQL,
            ['sejour' => (string) $sejour->getId()],
        );
    }
}
