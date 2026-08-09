<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:donnees:purger', description: 'Supprime les comptes désactivés et les situations particulières arrivés à expiration.')]
final class PurgerDonneesExpireesCommand extends Command
{
    public function __construct(private readonly Connection $connexion)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resultats = $this->connexion->transactional(function (Connection $connexion): array {
            $situations = $connexion->executeStatement(
                "DELETE FROM campement.situation_particuliere situation
                 USING campement.sejour sejour
                 WHERE situation.sejour_id = sejour.id
                   AND sejour.date_fin <= CURRENT_DATE - INTERVAL '14 days'",
            );
            $comptes = $connexion->executeStatement(
                "DELETE FROM campement.utilisateur
                 WHERE actif = FALSE
                   AND desactive_at <= CURRENT_TIMESTAMP - INTERVAL '1 month'",
            );

            return ['situations' => $situations, 'comptes' => $comptes];
        });

        $output->writeln(sprintf(
            '<info>Purge terminée : %d situation(s), %d compte(s).</info>',
            $resultats['situations'],
            $resultats['comptes'],
        ));

        return Command::SUCCESS;
    }
}
