<?php

declare(strict_types=1);

namespace App\Tests\Functional\Maintenance;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class PurgerDonneesExpireesCommandTest extends KernelTestCase
{
    public function testLesMouvementsSontReattribuesAvantLaSuppressionDuCompte(): void
    {
        self::bootKernel();
        $connexion = static::getContainer()->get(Connection::class);
        $utilisateurId = Uuid::v7()->toRfc4122();
        $mouvementId = Uuid::v7()->toRfc4122();

        $connexion->executeStatement(
            "INSERT INTO campement.utilisateur
                (id, email, mot_de_passe, prenom, nom, roles, actif, desactive_at)
             VALUES
                (:id, :email, '!', 'Ancien', 'Utilisateur', '[\"ROLE_ADMIN\"]'::jsonb, FALSE, CURRENT_TIMESTAMP - INTERVAL '2 months')",
            ['id' => $utilisateurId, 'email' => 'purge-'.bin2hex(random_bytes(6)).'@example.test'],
        );
        $connexion->executeStatement(
            "INSERT INTO campement.mouvement_stock
                (id, sejour_id, utilisateur_id, type_mouvement_id, origine_mouvement_id, annule_at, annule_par_id, motif_annulation)
             SELECT :mouvement, sejour.id, :utilisateur, type_mouvement.id, origine_mouvement.id,
                    CURRENT_TIMESTAMP, :utilisateur, 'Annulation de test'
             FROM campement.sejour sejour
             CROSS JOIN campement.type_mouvement type_mouvement
             CROSS JOIN campement.origine_mouvement origine_mouvement
             WHERE type_mouvement.code = 'ENTREE'
               AND origine_mouvement.code = 'CORRECTION'
             LIMIT 1",
            ['mouvement' => $mouvementId, 'utilisateur' => $utilisateurId],
        );

        try {
            $application = new Application(self::$kernel);
            $tester = new CommandTester($application->find('app:donnees:purger'));
            self::assertSame(0, $tester->execute([]));

            $utilisateurHistorique = $connexion->fetchOne(
                "SELECT id FROM campement.utilisateur WHERE email = 'historique@campement.local'",
            );
            self::assertIsString($utilisateurHistorique);
            self::assertFalse($connexion->fetchOne(
                'SELECT id FROM campement.utilisateur WHERE id = :id',
                ['id' => $utilisateurId],
            ));
            self::assertSame(
                ['utilisateur_id' => $utilisateurHistorique, 'annule_par_id' => $utilisateurHistorique],
                $connexion->fetchAssociative(
                    'SELECT utilisateur_id::text, annule_par_id::text FROM campement.mouvement_stock WHERE id = :id',
                    ['id' => $mouvementId],
                ),
            );
        } finally {
            $connexion->executeStatement(
                'DELETE FROM campement.mouvement_stock WHERE id = :id',
                ['id' => $mouvementId],
            );
            $connexion->executeStatement(
                'DELETE FROM campement.utilisateur WHERE id = :id',
                ['id' => $utilisateurId],
            );
        }
    }
}
