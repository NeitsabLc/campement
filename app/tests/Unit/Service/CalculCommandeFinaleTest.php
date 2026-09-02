<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Menu;
use App\Entity\Sejour;
use App\Entity\Unite;
use App\Service\CalculCommandeFinale;
use App\Service\ConversionConditionnement;
use PHPUnit\Framework\TestCase;

final class CalculCommandeFinaleTest extends TestCase
{
    public function testElleCumuleLaPeriodeEtDeduitLesRepasPrecedentsDuStock(): void
    {
        $date = new \DateTimeImmutable('2026-08-14');
        $sejour = new Sejour('Test', $date, $date->modify('+2 days'));
        $kilogramme = new Unite('kilogramme', 'kg');
        $tomates = (new Denree($sejour))->setNom('Tomates')->setUniteReference($kilogramme)->setUniteInventaire($kilogramme);
        $commandes = [];
        foreach ([2.0, 4.0, 6.0] as $index => $quantite) {
            $commandes[] = [
                'menu' => (new Menu())->setSejour($sejour)->setDateMenu($date->modify(sprintf('+%d days', $index))),
                'lignes' => [[
                    'denree' => $tomates,
                    'regime' => null,
                    'quantite' => $quantite,
                    'unite' => $kilogramme,
                ]],
            ];
        }
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        $resultat = (new CalculCommandeFinale($conversion))->calculer(
            $commandes,
            [(string) $tomates->getId() => ['entrees' => 10.0, 'sorties' => 1.0]],
            [],
            0,
            1,
            2,
        );

        self::assertSame(10.0, $resultat[0]['besoin']);
        self::assertSame(7.0, $resultat[0]['stock_previsionnel']);
        self::assertSame(3.0, $resultat[0]['quantite_commande']);
    }
}
