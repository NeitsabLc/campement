<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Denree;
use App\Entity\Sejour;
use App\Repository\MouvementStockLigneConditionnementRepository;
use App\Repository\MouvementStockLigneRepository;
use App\Repository\ReferenceFournisseurConditionnementRepository;

final class CalculStockDynamique
{
    public function __construct(
        private readonly MouvementStockLigneRepository $lignes,
        private readonly MouvementStockLigneConditionnementRepository $details,
        private readonly ReferenceFournisseurConditionnementRepository $conditionnements,
        private readonly ConversionConditionnement $conversion,
    ) {
    }

    /**
     * @param list<Denree> $denrees
     *
     * @return array<string, array{entrees: float, sorties: float}> indexé par identifiant de denrée
     */
    public function pourDenrees(Sejour $sejour, array $denrees): array
    {
        $stocks = [];
        foreach ($denrees as $denree) {
            $stocks[(string) $denree->getId()] = ['entrees' => 0.0, 'sorties' => 0.0];
        }
        if ([] === $denrees) {
            return $stocks;
        }

        $lignes = array_values(array_filter(
            $this->lignes->findPourGestion($sejour),
            static fn ($ligne): bool => isset($stocks[(string) $ligne->getDenree()->getId()]),
        ));
        $detailsParLigne = [];
        foreach ($this->details->findPourLignes($lignes) as $detail) {
            $detailsParLigne[(string) $detail->getMouvementStockLigne()->getId()][(string) $detail->getConditionnement()->getId()] = $detail->getQuantite();
        }

        $niveaux = $this->conditionnements->findPourDenrees($denrees);
        $niveauxParReference = [];
        foreach ($niveaux as $niveau) {
            $niveauxParReference[(string) $niveau->getReferenceFournisseur()->getId()][] = $niveau;
        }
        foreach ($lignes as $ligne) {
            $mouvement = $ligne->getMouvementStock();
            if ($mouvement->isAnnule()) {
                continue;
            }
            $denree = $ligne->getDenree();
            $denreeId = (string) $denree->getId();
            $quantitesSaisies = $detailsParLigne[(string) $ligne->getId()] ?? [];
            $reference = $ligne->getReferenceFournisseur();
            if (null !== $reference && [] !== $quantitesSaisies) {
                $quantiteInventaire = $this->conversion->quantiteEntreeInventaire(
                    $denree,
                    $niveauxParReference[(string) $reference->getId()] ?? [],
                    $quantitesSaisies,
                    $niveaux,
                );
            } else {
                $conditionnement = $ligne->getConditionnementSaisie();
                $quantiteSaisie = $ligne->getQuantiteSaisie();
                if (null === $conditionnement || null === $quantiteSaisie) {
                    throw new \LogicException(sprintf('Le mouvement de « %s » ne contient aucune quantité native.', $denree->getNom()));
                }
                $quantiteInventaire = $this->conversion->convertirAvecNiveaux(
                    $denree,
                    $conditionnement,
                    $denree->getUniteInventaire(),
                    (float) $quantiteSaisie,
                    $niveaux,
                );
            }

            $cle = 'ENTREE' === $mouvement->getTypeMouvement()->getCode() ? 'entrees' : 'sorties';
            $stocks[$denreeId][$cle] += $quantiteInventaire;
        }

        return $stocks;
    }
}
