<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\Denree;
use App\Entity\Unite;
use App\Repository\ReferenceFournisseurConditionnementRepository;
use App\Repository\ReferenceFournisseurRepository;
use Collator;

final class ConversionConditionnement
{
    public function __construct(private ReferenceFournisseurRepository $references, private ReferenceFournisseurConditionnementRepository $niveaux) {}

    /** @return list<Unite> */
    public function conditionnementsPour(Denree $denree): array
    {
        $resultat = [(string) $denree->getUniteReference()->getId() => $denree->getUniteReference()];
        foreach ($this->references->findPourDenree($denree) as $reference) {
            if (!$reference->isActif()) continue;
            foreach ($this->niveaux->findPourReference($reference) as $niveau) {
                $resultat[(string) $niveau->getConditionnement()->getId()] = $niveau->getConditionnement();
            }
        }
        $resultat = array_values($resultat);
        $collator = new Collator('fr_FR');
        usort($resultat, static fn (Unite $a, Unite $b): int => $collator->compare($a->getNom(), $b->getNom()));

        return $resultat;
    }

    /** Plus petit contenu connu, exprimé dans l'unité physique terminale de la denrée. */
    public function facteurMinimal(Denree $denree, Unite $conditionnement): ?float
    {
        if ($conditionnement === $denree->getUniteReference()) return 1.0;
        $facteurs = [];
        foreach ($this->references->findPourDenree($denree) as $reference) {
            if (!$reference->isActif()) continue;
            $liste = $this->niveaux->findPourReference($reference);
            $facteur = 1.0;
            for ($i = count($liste) - 1; $i >= 0; --$i) {
                $facteur *= (float) $liste[$i]->getQuantiteContenu();
                if ($liste[$i]->getConditionnement() === $conditionnement) $facteurs[] = $facteur;
            }
        }
        return [] === $facteurs ? null : min($facteurs);
    }

    public function versUniteReference(Denree $denree, Unite $conditionnement, float $quantite): float
    {
        return $quantite * ($this->facteurMinimal($denree, $conditionnement) ?? 1.0);
    }

    public function depuisUniteReference(Denree $denree, Unite $conditionnement, float $quantite): float
    {
        return $quantite / ($this->facteurMinimal($denree, $conditionnement) ?? 1.0);
    }

    public function stockInventaire(Denree $denree, float $entreesReference, float $sortiesReference): int
    {
        $entrees = $this->quantiteInventaire($denree, $entreesReference);
        $sorties = $this->quantiteInventaire($denree, $sortiesReference);

        return $entrees - $sorties;
    }

    public function quantiteInventaire(Denree $denree, float $quantiteReference): int
    {
        $facteur = $this->facteurMinimal($denree, $denree->getUniteInventaire()) ?? 1.0;

        return (int) ceil($quantiteReference / $facteur);
    }
}
