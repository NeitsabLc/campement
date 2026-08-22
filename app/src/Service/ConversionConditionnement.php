<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Denree;
use App\Entity\ReferenceFournisseurConditionnement;
use App\Entity\Unite;
use App\Repository\ReferenceFournisseurConditionnementRepository;
use App\Repository\ReferenceFournisseurRepository;

final class ConversionConditionnement
{
    public function __construct(private ReferenceFournisseurRepository $references, private ReferenceFournisseurConditionnementRepository $niveaux)
    {
    }

    /** @return list<Unite> */
    public function conditionnementsPour(Denree $denree): array
    {
        $resultat = [(string) $denree->getUniteReference()->getId() => $denree->getUniteReference()];
        foreach ($this->references->findPourDenree($denree) as $reference) {
            if (!$reference->isActif()) {
                continue;
            }
            foreach ($this->niveaux->findPourReference($reference) as $niveau) {
                $resultat[(string) $niveau->getConditionnement()->getId()] = $niveau->getConditionnement();
            }
        }
        $resultat = array_values($resultat);
        $collator = new \Collator('fr_FR');
        usort($resultat, static fn (Unite $a, Unite $b): int => $collator->compare($a->getNom(), $b->getNom()));

        return $resultat;
    }

    /**
     * Charge en une seule requête les conditionnements de plusieurs denrées.
     *
     * @param list<Denree>                                   $denrees
     * @param list<ReferenceFournisseurConditionnement>|null $niveaux niveaux déjà chargés, le cas échéant
     *
     * @return array<string, list<Unite>> indexé par identifiant de denrée
     */
    public function conditionnementsPourDenrees(array $denrees, ?array $niveaux = null): array
    {
        $resultats = [];
        foreach ($denrees as $denree) {
            $resultats[(string) $denree->getId()] = [
                (string) $denree->getUniteReference()->getId() => $denree->getUniteReference(),
            ];
        }

        foreach ($niveaux ?? $this->niveaux->findActifsPourDenrees($denrees) as $niveau) {
            $denreeId = (string) $niveau->getReferenceFournisseur()->getDenree()->getId();
            $conditionnement = $niveau->getConditionnement();
            $resultats[$denreeId][(string) $conditionnement->getId()] = $conditionnement;
        }

        $collator = new \Collator('fr_FR');
        foreach ($resultats as &$conditionnements) {
            $conditionnements = array_values($conditionnements);
            usort($conditionnements, static fn (Unite $a, Unite $b): int => $collator->compare($a->getNom(), $b->getNom()));
        }
        unset($conditionnements);

        return $resultats;
    }

    /** Plus petit contenu connu, exprimé dans l'unité physique terminale de la denrée. */
    public function facteurMinimal(Denree $denree, Unite $conditionnement): ?float
    {
        // Une unité peut aussi être un niveau intermédiaire de la chaîne : la
        // configuration explicite doit alors primer sur l'identité d'unité.
        $facteursActifs = [];
        $facteursArchives = [];
        foreach ($this->references->findPourDenree($denree) as $reference) {
            $liste = $this->niveaux->findPourReference($reference);
            $facteur = 1.0;
            for ($i = count($liste) - 1; $i >= 0; --$i) {
                $facteur *= (float) $liste[$i]->getQuantiteContenu();
                if ($liste[$i]->getConditionnement() === $conditionnement) {
                    if ($reference->isActif()) {
                        $facteursActifs[] = $facteur;
                    } else {
                        $facteursArchives[] = $facteur;
                    }
                }
            }
        }

        if ([] !== $facteursActifs) {
            return min($facteursActifs);
        }

        if ([] !== $facteursArchives) {
            return min($facteursArchives);
        }

        return $conditionnement === $denree->getUniteReference() ? 1.0 : null;
    }

    public function versUniteReference(Denree $denree, Unite $conditionnement, float $quantite): float
    {
        return $quantite * $this->facteurRequis($denree, $conditionnement);
    }

    public function convertir(Denree $denree, Unite $source, Unite $cible, float $quantite): float
    {
        if ($source === $cible) {
            return $quantite;
        }

        return $quantite
            * $this->facteurRequis($denree, $source)
            / $this->facteurRequis($denree, $cible);
    }

    /**
     * Variante sans accès à la base, destinée aux écrans ayant déjà préchargé
     * tous les niveaux de conditionnement.
     *
     * @param list<ReferenceFournisseurConditionnement> $niveaux
     */
    public function depuisUniteReferenceAvecNiveaux(Denree $denree, Unite $conditionnement, float $quantite, array $niveaux): float
    {
        return $quantite / $this->facteurAvecNiveaux($denree, $conditionnement, $niveaux);
    }

    /**
     * @param list<ReferenceFournisseurConditionnement> $niveaux
     */
    public function convertirAvecNiveaux(Denree $denree, Unite $source, Unite $cible, float $quantite, array $niveaux): float
    {
        if ($source === $cible) {
            return $quantite;
        }

        return $quantite
            * $this->facteurAvecNiveaux($denree, $source, $niveaux)
            / $this->facteurAvecNiveaux($denree, $cible, $niveaux);
    }

    /**
     * @param list<ReferenceFournisseurConditionnement> $niveaux
     */
    private function facteurAvecNiveaux(Denree $denree, Unite $conditionnement, array $niveaux): float
    {
        $parReference = [];
        foreach ($niveaux as $niveau) {
            if ($niveau->getReferenceFournisseur()->getDenree() === $denree) {
                $parReference[(string) $niveau->getReferenceFournisseur()->getId()][] = $niveau;
            }
        }

        $facteursActifs = [];
        $facteursArchives = [];
        foreach ($parReference as $niveauxReference) {
            $facteur = 1.0;
            for ($i = count($niveauxReference) - 1; $i >= 0; --$i) {
                $facteur *= (float) $niveauxReference[$i]->getQuantiteContenu();
                if ($niveauxReference[$i]->getConditionnement() === $conditionnement) {
                    if ($niveauxReference[$i]->getReferenceFournisseur()->isActif()) {
                        $facteursActifs[] = $facteur;
                    } else {
                        $facteursArchives[] = $facteur;
                    }
                }
            }
        }

        $facteurs = [] !== $facteursActifs ? $facteursActifs : $facteursArchives;
        if ([] === $facteurs) {
            if ($conditionnement === $denree->getUniteReference()) {
                return 1.0;
            }

            throw new \LogicException(sprintf('Aucune conversion de « %s » vers l’unité de référence de « %s ».', $conditionnement->getNom(), $denree->getNom()));
        }

        return min($facteurs);
    }

    public function stockDepuisQuantitesInventaire(float $entreesInventaire, float $sortiesInventaire): int
    {
        return (int) floor($entreesInventaire - $sortiesInventaire);
    }

    private function facteurRequis(Denree $denree, Unite $conditionnement): float
    {
        $facteur = $this->facteurMinimal($denree, $conditionnement);
        if (null === $facteur) {
            throw new \LogicException(sprintf('Aucune conversion de « %s » vers l’unité de référence de « %s ».', $conditionnement->getNom(), $denree->getNom()));
        }

        return $facteur;
    }

    /**
     * Convertit les quantités natives d'une entrée conditionnée vers l'unité
     * d'inventaire actuelle de la denrée.
     *
     * @param list<ReferenceFournisseurConditionnement> $conditionnements
     * @param array<string, string>                     $quantitesSaisies
     * @param list<ReferenceFournisseurConditionnement> $tousLesNiveaux
     */
    public function quantiteEntreeInventaire(Denree $denree, array $conditionnements, array $quantitesSaisies, array $tousLesNiveaux): float
    {
        $facteurs = [];
        $facteur = 1.0;
        for ($i = count($conditionnements) - 1; $i >= 0; --$i) {
            $conditionnement = $conditionnements[$i];
            $facteur *= (float) $conditionnement->getQuantiteContenu();
            $facteurs[(string) $conditionnement->getId()] = $facteur;
        }

        $quantiteTerminale = 0.0;
        foreach ($conditionnements as $conditionnement) {
            $id = (string) $conditionnement->getId();
            if (!isset($quantitesSaisies[$id])) {
                continue;
            }
            $quantiteTerminale += (float) $quantitesSaisies[$id] * $facteurs[$id];
        }

        if ($quantiteTerminale <= 0) {
            throw new \LogicException(sprintf('Aucune quantité conditionnée enregistrée pour « %s ».', $denree->getNom()));
        }

        return $this->depuisUniteReferenceAvecNiveaux(
            $denree,
            $denree->getUniteInventaire(),
            $quantiteTerminale,
            $tousLesNiveaux,
        );
    }
}
