<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Recette;
use App\Entity\RecetteDenree;
use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use App\Entity\TypeRepas;
use App\Entity\Unite;
use App\Enum\RegimeAlimentaire;
use App\Service\PresentationMenu;
use PHPUnit\Framework\TestCase;

final class PresentationMenuTest extends TestCase
{
    public function testLaCategorieDesRecettesDependDuTypeDeRepas(): void
    {
        $presentation = new PresentationMenu();

        self::assertSame(['PETIT_DEJEUNER'], $presentation->categoriesRecettesPourRepas('PETIT_DEJEUNER'));
        self::assertSame(['GOUTER', 'DESSERT'], $presentation->categoriesRecettesPourRepas('GOUTER'));
        self::assertNull($presentation->categoriesRecettesPourRepas('DEJEUNER'));
        self::assertNull($presentation->categoriesRecettesPourRepas('DINER'));
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

    public function testLeRegimeDeLaRecetteEstTransmisAuxDonneesDuMenu(): void
    {
        $sejour = new Sejour('Test', new \DateTimeImmutable('2026-07-10'), new \DateTimeImmutable('2026-07-11'));
        $unite = new Unite('Gramme', 'g');
        $denree = (new Denree($sejour))->setNom('Protéines végétales')->setUniteReference($unite);
        $recette = (new Recette($sejour))->setNom('Plat végétarien');
        $recette->addDenree((new RecetteDenree())
            ->setDenree($denree)
            ->setConditionnement($unite)
            ->setRegime(RegimeAlimentaire::VEGETARIEN));

        $donnees = (new PresentationMenu())->recettesJson([$recette]);

        self::assertSame(
            RegimeAlimentaire::VEGETARIEN->value,
            $donnees[(string) $recette->getId()]['lignes'][0]['regime'],
        );
    }
}
