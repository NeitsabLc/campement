<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\Denree;
use App\Entity\ReferenceFournisseurConditionnement;
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

    /**
     * Charge en une seule requête les conditionnements de plusieurs denrées.
     *
     * @param list<Denree> $denrees
     * @param null|list<ReferenceFournisseurConditionnement> $niveaux niveaux déjà chargés, le cas échéant
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

        $collator = new Collator('fr_FR');
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

    /**
     * Variante sans accès à la base, destinée aux écrans ayant déjà préchargé
     * tous les niveaux de conditionnement.
     *
     * @param list<ReferenceFournisseurConditionnement> $niveaux
     */
    public function depuisUniteReferenceAvecNiveaux(Denree $denree, Unite $conditionnement, float $quantite, array $niveaux): float
    {
        if ($conditionnement === $denree->getUniteReference()) {
            return $quantite;
        }

        $parReference = [];
        foreach ($niveaux as $niveau) {
            if ($niveau->getReferenceFournisseur()->getDenree() === $denree) {
                $parReference[(string) $niveau->getReferenceFournisseur()->getId()][] = $niveau;
            }
        }

        $facteurs = [];
        foreach ($parReference as $niveauxReference) {
            $facteur = 1.0;
            for ($i = count($niveauxReference) - 1; $i >= 0; --$i) {
                $facteur *= (float) $niveauxReference[$i]->getQuantiteContenu();
                if ($niveauxReference[$i]->getConditionnement() === $conditionnement) {
                    $facteurs[] = $facteur;
                }
            }
        }

        return $quantite / ([] === $facteurs ? 1.0 : min($facteurs));
    }

    public function stockInventaire(Denree $denree, float $entreesReference, float $sortiesReference): int
    {
        $entrees = $this->quantiteInventaire($denree, $entreesReference);
        $sorties = $this->quantiteInventaire($denree, $sortiesReference);

        return $this->stockDepuisQuantitesInventaire($entrees, $sorties);
    }

    public function stockDepuisQuantitesInventaire(float $entreesInventaire, float $sortiesInventaire): int
    {
        return (int) ceil($entreesInventaire) - (int) ceil($sortiesInventaire);
    }

    public function quantiteInventaire(Denree $denree, float $quantiteReference): int
    {
        return (int) ceil($this->quantiteInventaireExacte($denree, $quantiteReference));
    }

    public function quantiteInventaireExacte(Denree $denree, float $quantiteReference): float
    {
        $facteur = $this->facteurMinimal($denree, $denree->getUniteInventaire()) ?? 1.0;

        return $quantiteReference / $facteur;
    }

    /**
     * Formate une quantité positive selon la précision stockée en base.
     * Une quantité réelle inférieure à 0,001 reste ainsi représentée par la
     * plus petite valeur positive disponible, au lieu d'être arrondie à zéro.
     */
    public function formaterQuantiteInventaire(float $quantite): string
    {
        if (!is_finite($quantite) || $quantite <= 0) {
            throw new \InvalidArgumentException("Quantité d'inventaire invalide.");
        }

        return number_format(max(0.001, $quantite), 3, '.', '');
    }

    /**
     * Affiche une entrée dans l'unité réellement saisie lorsque toutes ses lignes
     * utilisent l'unité d'inventaire. Cette quantité historique ne doit pas être
     * recalculée avec un conditionnement qui a pu être modifié depuis.
     *
     * @param list<ReferenceFournisseurConditionnement> $conditionnements
     * @param array<string, string>                      $quantitesSaisies
     */
    public function quantiteEntreeInventaire(Denree $denree, float $quantiteReference, array $conditionnements, array $quantitesSaisies): float
    {
        $facteurs = [];
        $facteur = 1.0;
        $facteurInventaire = null;
        for ($i = count($conditionnements) - 1; $i >= 0; --$i) {
            $conditionnement = $conditionnements[$i];
            $facteur *= (float) $conditionnement->getQuantiteContenu();
            $facteurs[(string) $conditionnement->getId()] = $facteur;
            if ($conditionnement->getConditionnement() === $denree->getUniteInventaire()) {
                $facteurInventaire = $facteur;
            }
        }
        if (null === $facteurInventaire) {
            return $this->quantiteInventaireExacte($denree, $quantiteReference);
        }

        $totalSaisi = 0.0;
        $nombreSaisi = 0;
        foreach ($conditionnements as $conditionnement) {
            $id = (string) $conditionnement->getId();
            if (!isset($quantitesSaisies[$id])) {
                continue;
            }
            ++$nombreSaisi;
            $totalSaisi += (float) $quantitesSaisies[$id] * $facteurs[$id] / $facteurInventaire;
        }

        return $nombreSaisi > 0 ? $totalSaisi : $this->quantiteInventaireExacte($denree, $quantiteReference);
    }
}
