<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\ReferenceFournisseurConditionnement;
use App\Entity\Unite;
use App\Service\ConversionConditionnement;
use PHPUnit\Framework\TestCase;

final class ConversionConditionnementTest extends TestCase
{
    public function testLeStockArronditSeparementLesEntreesEtLesSorties(): void
    {
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        // 4 cartons de 4 paquets donnent 16 paquets. Les sorties cumulées de
        // 1 000 g représentent 0,2 paquet de 5 000 g, arrondi à un paquet sorti.
        self::assertSame(15, $conversion->stockDepuisQuantitesInventaire(16.0, 0.2));
    }

    public function testUneEntreeSaisieEnCartonsConserveSaQuantiteHistorique(): void
    {
        $carton = new Unite('carton', 'carton');
        $denree = $this->createStub(Denree::class);
        $denree->method('getUniteInventaire')->willReturn($carton);

        $conditionnement = new ReferenceFournisseurConditionnement(
            $this->createStub(\App\Entity\ReferenceFournisseur::class),
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
            20_000.0,
            [$conditionnement],
            [(string) $conditionnement->getId() => '2.000'],
        ));
    }

    public function testQuatreCartonsRestentSeizePaquetsQuandLeGrammageDuPaquetChange(): void
    {
        $carton = new Unite('carton', 'carton');
        $paquet = new Unite('paquet', 'paquet');
        $gramme = new Unite('gramme', 'g');
        $reference = $this->createStub(\App\Entity\ReferenceFournisseur::class);
        $conditionnements = [
            new ReferenceFournisseurConditionnement($reference, 1, 'carton', '4', null, 'paquet', $carton),
            new ReferenceFournisseurConditionnement($reference, 2, 'paquet', '6000', null, 'gramme', $paquet),
            new ReferenceFournisseurConditionnement($reference, 3, 'gramme', '1', $gramme, null, $gramme),
        ];
        $denree = $this->createStub(Denree::class);
        $denree->method('getUniteInventaire')->willReturn($paquet);
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        self::assertSame(16.0, $conversion->quantiteEntreeInventaire(
            $denree,
            80_000.0,
            $conditionnements,
            [(string) $conditionnements[0]->getId() => '4.000'],
        ));
    }
}
