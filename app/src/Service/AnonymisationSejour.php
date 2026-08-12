<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Sejour;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class AnonymisationSejour
{
    public function __construct(
        private readonly Connection $connexion,
        private readonly EntityManagerInterface $entityManager,
        private readonly StockageDocumentParticipant $stockage,
    ) {
    }

    /**
     * Supprime toutes les données rattachables à une personne. Les groupes (unités
     * participantes) et les données d'intendance ne sont jamais touchés.
     */
    public function anonymiser(Sejour $sejour, bool $supprimerSituations = false): void
    {
        $id = (string) $sejour->getId();
        $fichiers = $this->connexion->fetchFirstColumn(
            'SELECT d.chemin_stockage FROM campement.document_participant d
             JOIN campement.participant p ON p.id = d.participant_id
             JOIN campement.groupe g ON g.id = p.groupe_id WHERE g.sejour_id = :sejour',
            ['sejour' => $id],
        );

        $this->connexion->transactional(function (Connection $connexion) use ($id, $supprimerSituations): void {
            if ($supprimerSituations) {
                $connexion->executeStatement('DELETE FROM campement.situation_particuliere WHERE sejour_id = :sejour', ['sejour' => $id]);
            } else {
                $connexion->executeStatement(
                    'DELETE FROM campement.situation_particuliere_participant spp USING campement.situation_particuliere s
                     WHERE spp.situation_particuliere_id = s.id AND s.sejour_id = :sejour',
                    ['sejour' => $id],
                );
            }
            // Les présences, documents et associations restantes sont supprimés par cascade.
            $connexion->executeStatement(
                'DELETE FROM campement.participant p USING campement.groupe g
                 WHERE p.groupe_id = g.id AND g.sejour_id = :sejour',
                ['sejour' => $id],
            );
            $connexion->executeStatement(
                'UPDATE campement.sejour SET actif = false, anonymise_at = NOW(), updated_at = NOW() WHERE id = :sejour',
                ['sejour' => $id],
            );
        });

        foreach ($fichiers as $fichier) {
            if (is_string($fichier) && '' !== $fichier) {
                $this->stockage->supprimer($fichier);
            }
        }
        $sejour->setActif(false)->marquerAnonymise();
        $this->entityManager->flush();
    }
}
