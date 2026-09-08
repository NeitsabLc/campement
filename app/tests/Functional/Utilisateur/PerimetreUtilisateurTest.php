<?php

declare(strict_types=1);

namespace App\Tests\Functional\Utilisateur;

use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Repository\SejourRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PerimetreUtilisateurTest extends WebTestCase
{
    public function testUnGestionnaireConserveLesAffectationsHorsDeSonPerimetre(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $gestionnaire = $container->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        $sejourAccessible = $container->get(SejourRepository::class)->findOneBy([
            'nom' => 'Séjour de développement',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        self::assertInstanceOf(Sejour::class, $sejourAccessible);

        $autreSejour = new Sejour(
            'Autre périmètre '.bin2hex(random_bytes(4)),
            new \DateTimeImmutable('2028-08-01'),
            new \DateTimeImmutable('2028-08-10'),
        );
        $cible = (new Utilisateur())
            ->setPrenom('Camille')
            ->setNom('Multi-séjours')
            ->setEmail('multi-'.bin2hex(random_bytes(6)).'@example.test')
            ->setPassword('mot-de-passe-inutilisable')
            ->setRole(Utilisateur::ROLE_GESTIONNAIRE)
            ->addSejourGere($sejourAccessible)
            ->addSejourGere($autreSejour);
        $entityManager->persist($autreSejour);
        $entityManager->persist($cible);
        $entityManager->flush();
        $cibleId = (string) $cible->getId();
        $autreSejourId = (string) $autreSejour->getId();

        try {
            $client->loginUser($gestionnaire);
            $crawler = $client->request(
                'GET',
                '/utilisateurs/'.$cibleId.'/modifier?sejour='.$sejourAccessible->getId(),
            );
            self::assertResponseIsSuccessful();
            $formulaire = $crawler->selectButton('Enregistrer l’utilisateur')->form([
                'prenom' => 'Camille',
                'nom' => 'Modifiée',
                'email' => $cible->getEmail(),
                'role' => Utilisateur::ROLE_GESTIONNAIRE,
            ]);
            $client->submit($formulaire);
            self::assertResponseRedirects('/utilisateurs?sejour='.$sejourAccessible->getId());

            $connexion = static::getContainer()->get(Connection::class);
            self::assertSame(2, (int) $connexion->fetchOne(
                'SELECT COUNT(*) FROM campement.utilisateur_sejour WHERE utilisateur_id = :utilisateur',
                ['utilisateur' => $cibleId],
            ));
            self::assertSame(1, (int) $connexion->fetchOne(
                'SELECT COUNT(*) FROM campement.utilisateur_sejour WHERE utilisateur_id = :utilisateur AND sejour_id = :sejour',
                ['utilisateur' => $cibleId, 'sejour' => $autreSejourId],
            ));
        } finally {
            $connexion = static::getContainer()->get(Connection::class);
            $connexion->executeStatement('DELETE FROM campement.utilisateur WHERE id = :id', ['id' => $cibleId]);
            $connexion->executeStatement('DELETE FROM campement.sejour WHERE id = :id', ['id' => $autreSejourId]);
        }
    }
}
