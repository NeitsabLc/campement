<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use App\Entity\TypeRepas;
use App\Service\PresentationMenu;
use PHPUnit\Framework\TestCase;

final class PresentationMenuTest extends TestCase
{
    public function testLaCategorieDesRecettesDependDuTypeDeRepas(): void
    {
        $presentation = new PresentationMenu();

        self::assertSame('PETIT_DEJEUNER', $presentation->categorieRecettesPourRepas('PETIT_DEJEUNER'));
        self::assertSame('GOUTER', $presentation->categorieRecettesPourRepas('GOUTER'));
        self::assertNull($presentation->categorieRecettesPourRepas('DEJEUNER'));
        self::assertNull($presentation->categorieRecettesPourRepas('DINER'));
    }

    public function testLeRepasSuivantRespecteLOrdreDesRepasPuisChangeDeJour(): void
    {
        $presentation = new PresentationMenu();
        $premierJour = new \DateTimeImmutable('2026-07-10');
        $dernierJour = new \DateTimeImmutable('2026-07-11');
        $sejour = new Sejour('Test', $premierJour, $dernierJour);
        $petitDejeuner = new SejourTypeRepas($sejour, new TypeRepas('PETIT_DEJEUNER', 'Petit-déjeuner', 1), 1);
        $dejeuner = new SejourTypeRepas($sejour, new TypeRepas('DEJEUNER', 'Déjeuner', 2), 2);
        $repas = [$petitDejeuner, $dejeuner];

        self::assertSame(
            ['date' => $premierJour, 'repas' => $dejeuner],
            $presentation->repasSuivant($premierJour, $petitDejeuner, $repas, $dernierJour),
        );
        $repasDuJourSuivant = $presentation->repasSuivant($premierJour, $dejeuner, $repas, $dernierJour);
        self::assertNotNull($repasDuJourSuivant);
        self::assertEquals($dernierJour, $repasDuJourSuivant['date']);
        self::assertSame($petitDejeuner, $repasDuJourSuivant['repas']);
        self::assertNull($presentation->repasSuivant($dernierJour, $dejeuner, $repas, $dernierJour));
    }
}
