<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Fournisseur;
use App\Entity\ReferenceFournisseur;
use App\Entity\ReferenceFournisseurConditionnement;
use App\Entity\Sejour;
use App\Entity\Unite;
use App\Service\ConversionConditionnement;
use PHPUnit\Framework\TestCase;

final class ConversionConditionnementTest extends TestCase
{
    public function testLeStockArronditLeSoldeFinalALentierInferieur(): void
    {
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        self::assertSame(-1, $conversion->stockDepuisQuantitesInventaire(6.0, 6.83));
        self::assertSame(15, $conversion->stockDepuisQuantitesInventaire(16.0, 0.2));
    }

    public function testUneEntreeSaisieEnCartonsConserveSaQuantiteHistorique(): void
    {
        $carton = new Unite('carton', 'carton');
        $denree = $this->createStub(Denree::class);
        $denree->method('getUniteInventaire')->willReturn($carton);
        $reference = $this->createStub(ReferenceFournisseur::class);
        $reference->method('getDenree')->willReturn($denree);
        $reference->method('isActif')->willReturn(true);

        $conditionnement = new ReferenceFournisseurConditionnement(
            $reference,
            1,
            'carton',
            '1',
            $carton,
            null,
            $carton,
        );
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        self::assertSame(2.0, $conversion->quantiteEntreeInventaire(
            $denree,
            [$conditionnement],
            [(string) $conditionnement->getId() => '2.000'],
            [$conditionnement],
        ));
    }

    public function testQuatreCartonsRestentSeizePaquetsQuandLeGrammageDuPaquetChange(): void
    {
        $carton = new Unite('carton', 'carton');
        $paquet = new Unite('paquet', 'paquet');
        $gramme = new Unite('gramme', 'g');
        $denree = $this->createStub(Denree::class);
        $denree->method('getUniteInventaire')->willReturn($paquet);
        $reference = $this->createStub(ReferenceFournisseur::class);
        $reference->method('getDenree')->willReturn($denree);
        $reference->method('isActif')->willReturn(true);
        $conditionnements = [
            new ReferenceFournisseurConditionnement($reference, 1, 'carton', '4', null, 'paquet', $carton),
            new ReferenceFournisseurConditionnement($reference, 2, 'paquet', '6000', null, 'gramme', $paquet),
            new ReferenceFournisseurConditionnement($reference, 3, 'gramme', '1', $gramme, null, $gramme),
        ];
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        self::assertSame(16.0, $conversion->quantiteEntreeInventaire(
            $denree,
            $conditionnements,
            [(string) $conditionnements[0]->getId() => '4.000'],
            $conditionnements,
        ));
    }

    public function testUneUniteDeReferenceIntermediaireUtiliseLeFacteurDeLaChaine(): void
    {
        $kilogramme = new Unite('kilogramme', 'kg');
        $gramme = new Unite('gramme', 'g');
        $sejour = new Sejour('Séjour', new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-31'));
        $denree = (new Denree($sejour))
            ->setNom('Carotte')
            ->setUniteReference($kilogramme)
            ->setUniteInventaire($kilogramme);
        $reference = new ReferenceFournisseur(new Fournisseur($sejour, 'Primeur'), $denree, null);
        $conditionnements = [
            new ReferenceFournisseurConditionnement($reference, 1, 'kilogramme', '1000', $gramme, null, $kilogramme),
            new ReferenceFournisseurConditionnement($reference, 2, 'gramme', '1', $gramme, null, $gramme),
        ];
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        $sorties = $conversion->convertirAvecNiveaux($denree, $gramme, $kilogramme, 1855.0, $conditionnements);

        self::assertEqualsWithDelta(1.855, $sorties, 0.000_001);
        self::assertSame(6, $conversion->stockDepuisQuantitesInventaire(7.9, $sorties));
    }
}
