<?php

declare(strict_types=1);

namespace App\Tests\Functional\Securite;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConnexionTest extends WebTestCase
{
    public function testLaPageDeConnexionEstPublique(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
        self::assertSelectorExists('a[href="/mot-de-passe-oublie"]');
    }

    public function testLaPageMotDePasseOublieEstPublique(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mot-de-passe-oublie');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mot de passe oublié');
    }

    public function testUnePageProtegeeRedirigeVersLaConnexion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testUnAdministrateurPeutSeConnecterAvecUneAdresseNormalisee(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $formulaire = $crawler->selectButton('Se connecter')->form([
            '_username' => '  ADMIN@CAMPEMENT.LOCAL  ',
            '_password' => 'Campement?2026!',
        ]);
        $client->submit($formulaire);

        self::assertResponseRedirects('/');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Bienvenue sur l’application Campement');
        self::assertSelectorTextContains('.user-summary', 'ROLE_ADMIN');
    }

    public function testUnMotDePasseIncorrectEstRefuse(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $formulaire = $crawler->selectButton('Se connecter')->form([
            '_username' => 'admin@campement.local',
            '_password' => 'mot-de-passe-incorrect',
        ]);
        $client->submit($formulaire);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'Identifiants incorrects.');
    }

    public function testLeCompteTechniqueNePeutPasSeConnecter(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $formulaire = $crawler->selectButton('Se connecter')->form([
            '_username' => 'saisie-consommation@campement.local',
            '_password' => 'Campement?2026!',
        ]);
        $client->submit($formulaire);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'Identifiants incorrects.');
    }

    public function testLaSortieDeConsommationNeRedirigePasVersLaConnexion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sortie-consommation');

        self::assertResponseIsSuccessful();
    }
}
