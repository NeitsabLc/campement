<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Groupe;
use App\Enum\RegimeAlimentaire;
use PHPUnit\Framework\TestCase;

final class GroupeRegimeTest extends TestCase
{
    public function testLesBesoinsSontDeterminesPourChaqueRegime(): void
    {
        $groupe = (new Groupe())
            ->setNombreVegetariens(3)
            ->setNombreSansLactose(0)
            ->setNombreSansGluten(1);

        self::assertTrue($groupe->aBesoinDuRegime(null));
        self::assertTrue($groupe->aBesoinDuRegime(RegimeAlimentaire::VEGETARIEN));
        self::assertFalse($groupe->aBesoinDuRegime(RegimeAlimentaire::SANS_LACTOSE));
        self::assertTrue($groupe->aBesoinDuRegime(RegimeAlimentaire::SANS_GLUTEN));
    }
}
