<?php

declare(strict_types=1);

namespace App\Tests\Functional\Securite;

use App\Repository\UtilisateurRepository;
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

        $politique = $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertNotNull($politique);
        self::assertStringContainsString("script-src 'self' 'nonce-", $politique);
        self::assertStringNotContainsString('cdn.jsdelivr.net', $politique);
        self::assertStringNotContainsString("'unsafe-inline'", $politique);
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

    public function testLeMotDePasseActuelEstExigePourUnChangementOrdinaire(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)
            ->findOneBy(['email' => 'admin@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/modifier-mon-mot-de-passe');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="mot_de_passe_actuel"][required]');

        $formulaire = $crawler->selectButton('Enregistrer mon mot de passe')->form([
            'mot_de_passe_actuel' => 'incorrect',
            'mot_de_passe' => 'Nouveau?Campement2026',
            'confirmation' => 'Nouveau?Campement2026',
        ]);
        $client->submit($formulaire);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'Le mot de passe actuel est incorrect.');
    }
}
