<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\Sejour;
use App\Entity\Unite;
use App\Enum\RegimeAlimentaire;
use App\Service\AffichageQuantite;
use App\Service\ListeCoursesPdf;
use PHPUnit\Framework\TestCase;

final class ListeCoursesPdfTest extends TestCase
{
    public function testLaFicheConserveSeulementLesRegimesNecessairesAuGroupe(): void
    {
        $sejour = new Sejour('Test', new \DateTimeImmutable('2026-07-10'), new \DateTimeImmutable('2026-07-11'));
        $unite = new Unite('Gramme', 'g');
        $denree = (new Denree($sejour))->setNom('Protéines')->setUniteReference($unite);
        $menu = (new Menu())->setSejour($sejour);
        foreach ([null, RegimeAlimentaire::VEGETARIEN, RegimeAlimentaire::SANS_GLUTEN] as $regime) {
            $menu->addDenree((new MenuDenree())
                ->setDenree($denree)
                ->setConditionnement($unite)
                ->setRegime($regime));
        }
        $groupe = (new Groupe())
            ->setSejour($sejour)
            ->setNom('Unité test')
            ->setType('farfadets')
            ->setNombreVegetariens(2)
            ->setNombreSansGluten(0);

        $service = new ListeCoursesPdf('/tmp', new AffichageQuantite());
        $methode = new \ReflectionMethod($service, 'fiche');
        $fiche = $methode->invoke($service, $menu, [$menu], $groupe, 'FARFADETS', 12, '#000');
        self::assertIsArray($fiche);
        $noms = array_column($fiche['lignes'], 'nom');

        self::assertContains('Protéines', $noms);
        self::assertContains('Protéines — Végétarien (2 pers.)', $noms);
        self::assertNotContains('Protéines — Sans gluten (0 pers.)', $noms);
    }
}
