<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AffichageQuantite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AffichageQuantiteTest extends TestCase
{
    #[DataProvider('quantites')]
    public function testAffichageParPersonne(float $quantite, string $attendu): void
    {
        self::assertSame($attendu, (new AffichageQuantite())->parPersonne($quantite));
    }

    public function testAffichageNombreConserveUneQuantiteDecimale(): void
    {
        self::assertSame('0,5', (new AffichageQuantite())->nombre('0.500'));
        self::assertSame('3', (new AffichageQuantite())->nombre('3.000'));
    }

    public static function quantites(): iterable
    {
        yield 'un demi' => [0.5, '1/2'];
        yield 'un tiers enregistré sur trois décimales' => [0.333, '1/3'];
        yield 'deux tiers enregistrés sur trois décimales' => [0.667, '2/3'];
        yield 'trois quarts' => [0.75, '3/4'];
        yield 'fraction non usuelle conservée en décimal' => [0.123, '0,123'];
        yield 'quantité supérieure à un conservée en décimal' => [1.25, '1,25'];
        yield 'entier' => [2.0, '2'];
        yield 'zéro' => [0.0, '0'];
    }
}
