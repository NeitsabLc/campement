<?php

declare(strict_types=1);

namespace App\Tests\Functional\Utilisateur;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InvitationUtilisateurTest extends WebTestCase
{
    public function testUneErreurDeValidationEstAfficheeAvecTurbo(): void
    {
        $client = static::createClient();
        $utilisateurs = static::getContainer()->get(UtilisateurRepository::class);
        $administrateur = $utilisateurs->findOneBy(['email' => 'admin@campement.local']);
        self::assertInstanceOf(Utilisateur::class, $administrateur);
        $client->loginUser($administrateur);

        $crawler = $client->request('GET', '/utilisateurs/ajouter');
        $formulaire = $crawler->selectButton('Enregistrer l’utilisateur')->form([
            'prenom' => 'Autre',
            'nom' => 'Administrateur',
            'email' => 'admin@campement.local',
            'role' => Utilisateur::ROLE_ADMIN,
        ]);
        $client->submit($formulaire);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Un utilisateur possède déjà cette adresse électronique.');
    }

    public function testLaCreationEnvoieUnLienDInvitationValable24Heures(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $utilisateurs = $container->get(UtilisateurRepository::class);
        $administrateur = $utilisateurs->findOneBy(['email' => 'admin@campement.local']);
        self::assertInstanceOf(Utilisateur::class, $administrateur);
        $client->loginUser($administrateur);

        $email = 'invitation-'.bin2hex(random_bytes(6)).'@example.test';
        $crawler = $client->request('GET', '/utilisateurs');
        $formulaire = $crawler->filter('[data-user-dialog] form')->form([
            'prenom' => 'Camille',
            'nom' => 'Invitation',
            'email' => $email,
            'role' => Utilisateur::ROLE_ADMIN,
        ]);
        $client->submit($formulaire);

        self::assertResponseRedirects('/utilisateurs');
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $message);
        self::assertEmailHtmlBodyContains($message, 'Ce lien d’invitation est valable pendant 24 heures.');
        self::assertEmailHtmlBodyContains($message, 'Je choisis mon mot de passe');
        self::assertEmailHtmlBodyNotContains($message, 'Mot de passe provisoire');

        $corps = $message->getHtmlBody();
        self::assertIsString($corps);
        self::assertStringContainsString('http://localhost:8080/reinitialiser-mot-de-passe/', $corps);
        self::assertMatchesRegularExpression('#/reinitialiser-mot-de-passe/[a-f0-9]{64}#', $corps);
        preg_match('#/reinitialiser-mot-de-passe/([a-f0-9]{64})#', $corps, $correspondances);
        $jeton = $correspondances[1] ?? '';

        $invite = $utilisateurs->findOneBy(['email' => $email]);
        self::assertInstanceOf(Utilisateur::class, $invite);
        self::assertTrue($invite->jetonReinitialisationEstValide($jeton, new \DateTimeImmutable('+23 hours 59 minutes')));
        self::assertFalse($invite->jetonReinitialisationEstValide($jeton, new \DateTimeImmutable('+24 hours 1 minute')));

        $entityManager = $container->get(EntityManagerInterface::class);
        $inviteGere = $entityManager->find(Utilisateur::class, $invite->getId());
        self::assertInstanceOf(Utilisateur::class, $inviteGere);
        $entityManager->remove($inviteGere);
        $entityManager->flush();
    }
}
