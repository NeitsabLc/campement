<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Groupe;
use App\Entity\GroupeRepas;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\PublicCible;
use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use App\Entity\TypeRepas;
use App\Entity\Unite;
use App\Enum\ModeRepasGroupe;
use App\Enum\RegimeAlimentaire;
use App\Service\CalculCommande;
use PHPUnit\Framework\TestCase;

final class CalculCommandeTest extends TestCase
{
    public function testLeCalculAppliqueRegimesMenusSpeciauxRepasNonPrisEtAbsences(): void
    {
        $date = new \DateTimeImmutable('2026-08-14');
        $sejour = new Sejour('Test', $date, $date->modify('+2 days'));
        $farfadets = (new PublicCible())->setCode('FARFADETS')->setLibelle('Farfadets');
        $adultes = (new PublicCible())->setCode('ADULTE')->setLibelle('Adulte');
        $sejour->addPublicCible($farfadets)->addPublicCible($adultes);
        $publics = [];
        foreach ($sejour->getPublicsCibles() as $configuration) {
            $publics[$configuration->getPublicCible()->getCode()] = $configuration;
        }

        $repas = new SejourTypeRepas($sejour, new TypeRepas('DEJEUNER', 'Déjeuner'));
        $gramme = new Unite('gramme', 'g');
        $piece = new Unite('pièce', 'pc');
        $farine = (new Denree($sejour))->setNom('Farine')->setUniteReference($gramme);
        $tofu = (new Denree($sejour))->setNom('Tofu')->setUniteReference($gramme);
        $haricots = (new Denree($sejour))->setNom('Haricots')->setUniteReference($gramme);
        $pain = (new Denree($sejour))->setNom('Pain')->setUniteReference($piece);

        $menu = (new Menu())->setSejour($sejour)->setDateMenu($date)->setSejourTypeRepas($repas)
            ->addDenree($this->ligne($farine, $gramme, null, $publics, 100, 150))
            ->addDenree($this->ligne($tofu, $gramme, RegimeAlimentaire::VEGETARIEN, $publics, 80, 120));
        $explo = (new Menu())->setSejour($sejour)->setSpecialCode('EXPLO')
            ->addDenree($this->ligne($haricots, $gramme, null, $publics, 50, 60));
        $piqueNique = (new Menu())->setSejour($sejour)->setSpecialCode('PIQUE_NIQUE_1')
            ->addDenree($this->ligne($pain, $piece, null, $publics, 2, 2));

        $normal = $this->groupe($sejour, 'Normal', $date, 10, 2, 3);
        $enExplo = $this->groupe($sejour, 'Explo', $date, 5, 0, 0);
        $enPiqueNique = $this->groupe($sejour, 'Pique-nique', $date, 4, 0, 0);
        $sansRepas = $this->groupe($sejour, 'Non pris', $date, 20, 0, 0);
        $absent = $this->groupe($sejour, 'Absent', $date->modify('+1 day'), 30, 0, 0);

        $commandes = (new CalculCommande())->calculer(
            [$menu, $explo, $piqueNique],
            [$normal, $enExplo, $enPiqueNique, $sansRepas, $absent],
            [
                new GroupeRepas($enExplo, $menu, ModeRepasGroupe::EXPLO),
                new GroupeRepas($enPiqueNique, $menu, ModeRepasGroupe::PIQUE_NIQUE_1),
                new GroupeRepas($sansRepas, $menu, ModeRepasGroupe::NON_PRIS),
            ],
        );

        self::assertCount(1, $commandes);
        self::assertSame(['Farine', 'Haricots', 'Pain', 'Tofu'], array_map(
            static fn (array $ligne): string => $ligne['denree']->getNom(),
            $commandes[0]['lignes'],
        ));
        self::assertSame([1300.0, 250.0, 8.0, 240.0], array_column($commandes[0]['lignes'], 'quantite'));
    }

    /** @param array<string, \App\Entity\SejourPublicCible> $publics */
    private function ligne(Denree $denree, Unite $unite, ?RegimeAlimentaire $regime, array $publics, int $jeune, int $adulte): MenuDenree
    {
        return (new MenuDenree())
            ->setDenree($denree)
            ->setConditionnement($unite)
            ->setRegime($regime)
            ->addQuantite((new MenuDenreeQuantite())->setSejourPublicCible($publics['FARFADETS'])->setQuantiteIndividuelle((string) $jeune))
            ->addQuantite((new MenuDenreeQuantite())->setSejourPublicCible($publics['ADULTE'])->setQuantiteIndividuelle((string) $adulte));
    }

    private function groupe(Sejour $sejour, string $nom, \DateTimeImmutable $date, int $jeunes, int $adultes, int $vegetariens): Groupe
    {
        return (new Groupe())
            ->setSejour($sejour)
            ->setNom($nom)
            ->setType('farfadets')
            ->setEffectifJeune($jeunes)
            ->setEffectifAdulte($adultes)
            ->setNombreVegetariens($vegetariens)
            ->setDateDebutPresence($date)
            ->setDateFinPresence($date);
    }
}
