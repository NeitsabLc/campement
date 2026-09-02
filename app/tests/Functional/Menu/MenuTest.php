<?php

declare(strict_types=1);

namespace App\Tests\Functional\Menu;

use App\Entity\Recette;
use App\Entity\Sejour;
use App\Repository\SejourTypeRepasRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MenuTest extends WebTestCase
{
    public function testLaGestionDesMenusNecessiteUneAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/menus');

        self::assertResponseRedirects('/login');
    }

    public function testUnGestionnaireAccedeALaGestionDesMenus(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'gestionnaire@campement.local',
            '_password' => 'Campement?2026!',
        ]));
        $client->followRedirect();

        $client->request('GET', '/menus');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Menus');
        self::assertSelectorExists('.meal-calendar[data-turbo-prefetch="false"]');
        self::assertSelectorExists('button.save-next-meal-button[name="action"][value="suivant"]');
        self::assertSelectorTextContains('.save-next-meal-button', 'Enregistrer et passer au repas suivant');
        self::assertSelectorExists('template select[data-field="regime"] option[value="VEGETARIEN"]');
        self::assertSelectorExists('select[data-field="conditionnement"][aria-label]');
        self::assertSelectorExists('input[data-public][data-public-label][aria-label]');
        self::assertSelectorExists('template select[data-field="conditionnement"][aria-label]');
        self::assertSelectorExists('template input[data-public][data-public-label][aria-label]');
    }

    public function testUnUtilisateurGroupeConsulteLesMenusEnLectureSeule(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'groupe@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/menus');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.group-menu-heading', 'Menus');
        self::assertSelectorTextNotContains('.group-menu-heading', 'Lecture seule');
        self::assertSelectorExists('.group-menu-date-nav');
        self::assertSelectorExists('.group-menu-meal');
        self::assertSelectorNotExists('.save-meal-button');

        $client->request('POST', '/menus', ['_token' => 'lecture-seule']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testLesRecettesSontFiltreesPourLePetitDejeunerEtLeGouter(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $utilisateur = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $sejour = $utilisateur->getDernierSejour();
        if (!$sejour instanceof Sejour || !$sejour->isActif()) {
            $sejour = $utilisateur->getSejoursGeres()->filter(static fn (Sejour $candidat): bool => $candidat->isActif())->first();
        }
        self::assertInstanceOf(Sejour::class, $sejour);

        $suffixe = bin2hex(random_bytes(4));
        $petitDejeuner = (new Recette($sejour))->setNom('Petit-déjeuner '.$suffixe)->setCategorie('PETIT_DEJEUNER');
        $gouter = (new Recette($sejour))->setNom('Goûter '.$suffixe)->setCategorie('GOUTER');
        $dessert = (new Recette($sejour))->setNom('Dessert '.$suffixe)->setCategorie('DESSERT');
        $plat = (new Recette($sejour))->setNom('Plat '.$suffixe)->setCategorie('PLAT');
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($petitDejeuner);
        $entityManager->persist($gouter);
        $entityManager->persist($dessert);
        $entityManager->persist($plat);
        $entityManager->flush();

        $repas = [];
        foreach ($container->get(SejourTypeRepasRepository::class)->findActifsPourSejour($sejour) as $configuration) {
            $repas[$configuration->getTypeRepas()->getCode()] = (string) $configuration->getId();
        }
        self::assertArrayHasKey('PETIT_DEJEUNER', $repas);
        self::assertArrayHasKey('GOUTER', $repas);

        $client->request('GET', '/menus?repas='.$repas['PETIT_DEJEUNER']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-recipe-picker]', $petitDejeuner->getNom());
        self::assertSelectorTextNotContains('[data-recipe-picker]', $gouter->getNom());
        self::assertSelectorTextNotContains('[data-recipe-picker]', $dessert->getNom());
        self::assertSelectorTextNotContains('[data-recipe-picker]', $plat->getNom());

        $client->request('GET', '/menus?repas='.$repas['GOUTER']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-recipe-picker]', $gouter->getNom());
        self::assertSelectorTextContains('[data-recipe-picker]', $dessert->getNom());
        self::assertSelectorTextNotContains('[data-recipe-picker]', $petitDejeuner->getNom());
        self::assertSelectorTextNotContains('[data-recipe-picker]', $plat->getNom());
    }
}
