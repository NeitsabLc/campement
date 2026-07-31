<?php

declare(strict_types=1);

namespace App\Tests\Functional\Menu;

use App\Repository\UtilisateurRepository;
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
        self::assertSelectorTextContains('h1', 'Gestion des menus');
        self::assertSelectorExists('.meal-calendar[data-turbo-prefetch="false"]');
    }

    public function testUnUtilisateurGroupeConsulteLesMenusEnLectureSeule(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'groupe@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/menus');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.menus-heading', 'Lecture seule');
        self::assertSelectorExists('.menu-readonly-scope[disabled]');
        self::assertSelectorNotExists('.save-meal-button');

        $client->request('POST', '/menus', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')]);
        self::assertResponseStatusCodeSame(403);
    }
}
