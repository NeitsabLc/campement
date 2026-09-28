<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commande;

use App\Command\AnonymiserSejoursTerminesCommand;
use App\Repository\SejourRepository;
use App\Service\AnonymisationSejour;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Uid\Uuid;

final class AnonymiserSejoursTerminesCommandTest extends KernelTestCase
{
    public function testUnePanneSmtpNeBloquePasLanonymisation(): void
    {
        static::bootKernel();
        $conteneur = static::getContainer();
        $connexion = $conteneur->get(Connection::class);
        $entityManager = $conteneur->get(EntityManagerInterface::class);
        self::assertInstanceOf(Connection::class, $connexion);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $connexion->beginTransaction();
        try {
            $connexion->executeStatement(
                'UPDATE campement.sejour SET anonymise_at = NOW(), actif = false WHERE date_fin <= CURRENT_DATE - 2',
            );
            $sejourId = (string) Uuid::v7();
            $connexion->insert('campement.sejour', [
                'id' => $sejourId,
                'nom' => 'Séjour test panne SMTP',
                'date_debut' => '2026-01-01',
                'date_fin' => '2026-01-03',
            ]);
            $gestionnaireId = $connexion->fetchOne(
                "SELECT id FROM campement.utilisateur WHERE email = 'gestionnaire@campement.local'",
            );
            self::assertIsString($gestionnaireId);
            $connexion->insert('campement.utilisateur_sejour', [
                'utilisateur_id' => $gestionnaireId,
                'sejour_id' => $sejourId,
            ]);

            $mailer = $this->createMock(MailerInterface::class);
            $mailer->expects(self::once())
                ->method('send')
                ->willThrowException(new TransportException('SMTP indisponible'));
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::once())
                ->method('warning')
                ->with(
                    'Notification d’anonymisation non envoyée.',
                    self::callback(static fn (array $contexte): bool => $sejourId === $contexte['sejour_id']
                        && $contexte['exception'] instanceof TransportException),
                );

            $commande = new AnonymiserSejoursTerminesCommand(
                $conteneur->get(SejourRepository::class),
                $conteneur->get(AnonymisationSejour::class),
                $mailer,
                $logger,
                'no-reply@campement.test',
                'Campement',
            );
            $testeur = new CommandTester($commande);

            self::assertSame(Command::SUCCESS, $testeur->execute([]));
            self::assertStringContainsString(
                'Séjour test panne SMTP anonymisé, mais la notification n’a pas pu être envoyée.',
                $testeur->getDisplay(),
            );
            self::assertFalse($connexion->fetchOne('SELECT actif FROM campement.sejour WHERE id = :id', ['id' => $sejourId]));
            self::assertNotNull($connexion->fetchOne('SELECT anonymise_at FROM campement.sejour WHERE id = :id', ['id' => $sejourId]));
        } finally {
            $entityManager->clear();
            $connexion->rollBack();
        }
    }
}
