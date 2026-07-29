<?php

declare(strict_types=1);

namespace App\Tests\Functional\Menu;

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
}
