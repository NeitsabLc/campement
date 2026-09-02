<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Denree;
use App\Entity\Fournisseur;
use App\Entity\ReferenceFournisseur;
use App\Entity\Unite;

final class RegroupementCommandeFournisseur
{
    /**
     * @param list<array{denree: Denree, besoin: float, stock_previsionnel: float, quantite_commande: float, unite: Unite}> $commande
     * @param list<ReferenceFournisseur> $references
     *
     * @return list<array{nom: string, type: string, lignes: list<array{denree: Denree, besoin: float, stock_previsionnel: float, quantite_commande: float, unite: Unite, fournisseurs: list<Fournisseur>, references_produit: list<string>}>}>
     */
    public function regrouper(array $commande, array $references): array
    {
        $referencesParDenree = [];
        foreach ($references as $reference) {
            $denreeId = (string) $reference->getDenree()->getId();
            $fournisseur = $reference->getFournisseur();
            $fournisseurId = (string) $fournisseur->getId();
            $referencesParDenree[$denreeId][$fournisseurId] ??= ['fournisseur' => $fournisseur, 'references' => []];
            $referenceProduit = trim((string) $reference->getReference());
            if ('' !== $referenceProduit) {
                $referencesParDenree[$denreeId][$fournisseurId]['references'][$referenceProduit] = $referenceProduit;
            }
        }

        $groupes = [];
        foreach ($commande as $ligne) {
            $referencesDenree = array_values($referencesParDenree[(string) $ligne['denree']->getId()] ?? []);
            $fournisseurs = array_map(static fn (array $reference) => $reference['fournisseur'], $referencesDenree);
            $referencesProduit = [];
            foreach ($referencesDenree as $referenceDenree) {
                $referencesProduit += $referenceDenree['references'];
            }
            $ligne['fournisseurs'] = $fournisseurs;
            $ligne['references_produit'] = array_values($referencesProduit);

            if (1 === count($fournisseurs)) {
                $cle = 'fournisseur_'.(string) $fournisseurs[0]->getId();
                $nom = $fournisseurs[0]->getNom();
                $type = 'fournisseur';
            } elseif ([] === $fournisseurs) {
                $cle = 'sans_fournisseur';
                $nom = 'Sans fournisseur';
                $type = 'sans_fournisseur';
            } else {
                $cle = 'fournisseur_a_choisir';
                $nom = 'Fournisseur à choisir';
                $type = 'fournisseur_a_choisir';
            }
            $groupes[$cle] ??= ['nom' => $nom, 'type' => $type, 'lignes' => []];
            $groupes[$cle]['lignes'][] = $ligne;
        }

        uasort($groupes, static function (array $a, array $b): int {
            $ordre = ['fournisseur' => 0, 'fournisseur_a_choisir' => 1, 'sans_fournisseur' => 2];

            return ($ordre[$a['type']] <=> $ordre[$b['type']]) ?: strnatcasecmp($a['nom'], $b['nom']);
        });

        return array_values($groupes);
    }
}
