<?php

declare(strict_types=1);

namespace App\Tests\Functional\Securite;

use App\Entity\Groupe;
use App\Entity\Sejour;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccesSansSejourTest extends WebTestCase
{
    public function testUnCompteGroupeSansSejourAccessibleVoitUneInformationSansMenuMetier(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $sejour = (new Sejour(
            'Séjour inactif sans accès '.bin2hex(random_bytes(4)),
            new \DateTimeImmutable('2027-07-01'),
            new \DateTimeImmutable('2027-07-15'),
        ))->setActif(false);
        $groupe = (new Groupe())
            ->setSejour($sejour)
            ->setNom('Unité sans accès')
            ->setType('scouts-guides')
            ->setDateDebutPresence($sejour->getDateDebut())
            ->setDateFinPresence($sejour->getDateFin());
        $utilisateur = (new Utilisateur())
            ->setGroupe($groupe)
            ->setEmail('groupe-sans-sejour-'.bin2hex(random_bytes(4)).'@example.test')
            ->setPassword('mot-de-passe-inutilisable')
            ->setPrenom('Groupe')
            ->setNom('Sans accès')
            ->setRole(Utilisateur::ROLE_GROUPE);
        $entityManager->persist($sejour);
        $entityManager->persist($groupe);
        $entityManager->persist($utilisateur);
        $entityManager->flush();
        $utilisateurId = (string) $utilisateur->getId();
        $groupeId = (string) $groupe->getId();
        $sejourId = (string) $sejour->getId();

        try {
            $client->loginUser($utilisateur);
            $client->request('GET', '/');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h2', 'Aucun séjour accessible');
            self::assertSelectorTextContains('main', 'Votre compte ne possède actuellement aucun droit sur un séjour.');
            self::assertSelectorTextContains('main', 'Contactez les responsables de votre séjour');
            self::assertSelectorCount(0, '.sidebar__nav a');

            foreach (['/groupes', '/stocks'] as $chemin) {
                $client->request('GET', $chemin);
                self::assertResponseRedirects('/');
                $client->followRedirect();
                self::assertSelectorTextContains('[role="alert"]', 'Votre compte ne possède actuellement aucun droit sur un séjour.');
                self::assertSelectorCount(0, '.sidebar__nav a');
            }
        } finally {
            $connexion = static::getContainer()->get(Connection::class);
            $connexion->executeStatement('DELETE FROM campement.utilisateur WHERE id = :id', ['id' => $utilisateurId]);
            $connexion->executeStatement('DELETE FROM campement.groupe WHERE id = :id', ['id' => $groupeId]);
            $connexion->executeStatement('DELETE FROM campement.sejour WHERE id = :id', ['id' => $sejourId]);
        }
    }
}
