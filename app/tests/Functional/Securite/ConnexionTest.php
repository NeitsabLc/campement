<?php

declare(strict_types=1);

namespace App\Tests\Functional\Securite;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConnexionTest extends WebTestCase
{
    public function testLaPageDeConnexionEstPublique(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ravi de vous revoir');
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

    public function testUnUtilisateurConnecteNePeutPasUtiliserLeLienDUnAutreCompte(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $jeton = bin2hex(random_bytes(32));
        $invite = (new Utilisateur())
            ->setPrenom('Compte')
            ->setNom('Invité')
            ->setEmail('lien-autre-compte-'.bin2hex(random_bytes(5)).'@example.test')
            ->setRole(Utilisateur::ROLE_ADMIN)
            ->setPassword('mot-de-passe-inutilisable')
            ->definirJetonReinitialisation($jeton, new \DateTimeImmutable('+24 hours'));
        $entityManager->persist($invite);
        $entityManager->flush();
        $inviteId = $invite->getId();

        $client->request('GET', '/reinitialiser-mot-de-passe/'.$jeton);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.password-card img[alt="Scouts et Guides de France"]');
        self::assertSelectorTextContains('h1', 'Nouveau mot de passe');
        self::assertSelectorExists('input[name="mot_de_passe"]');

        $administrateur = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'admin@campement.local']);
        self::assertInstanceOf(Utilisateur::class, $administrateur);
        $client->loginUser($administrateur);
        $client->request('GET', '/reinitialiser-mot-de-passe/'.$jeton);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'Ce lien est associé à un autre compte.');

        $inviteGere = $container->get(EntityManagerInterface::class)->find(Utilisateur::class, $inviteId);
        self::assertInstanceOf(Utilisateur::class, $inviteGere);
        $container->get(EntityManagerInterface::class)->remove($inviteGere);
        $container->get(EntityManagerInterface::class)->flush();
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
