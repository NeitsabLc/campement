<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:donnees:purger', description: 'Réattribue puis supprime les comptes désactivés et purge les situations particulières arrivées à expiration.')]
final class PurgerDonneesExpireesCommand extends Command
{
    private const UTILISATEUR_HISTORIQUE_EMAIL = 'historique@campement.local';

    public function __construct(private readonly Connection $connexion)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resultats = $this->connexion->transactional(function (Connection $connexion): array {
            $utilisateurHistorique = $connexion->fetchOne(
                "SELECT id
                 FROM campement.utilisateur
                 WHERE email = :email
                   AND roles = '[\"ROLE_TECHNIQUE\"]'::jsonb",
                ['email' => self::UTILISATEUR_HISTORIQUE_EMAIL],
            );
            if (false === $utilisateurHistorique) {
                throw new \RuntimeException('Le compte technique de conservation de l’historique est absent.');
            }

            $situations = $connexion->executeStatement(
                "DELETE FROM campement.situation_particuliere situation
                 USING campement.sejour sejour
                 WHERE situation.sejour_id = sejour.id
                   AND sejour.date_fin <= CURRENT_DATE - INTERVAL '14 days'",
            );
            $mouvements = $connexion->executeStatement(
                "UPDATE campement.mouvement_stock
                 SET utilisateur_id = :utilisateur_historique
                 WHERE utilisateur_id IN (
                     SELECT id
                     FROM campement.utilisateur
                     WHERE actif = FALSE
                       AND desactive_at <= CURRENT_TIMESTAMP - INTERVAL '1 month'
                 )",
                ['utilisateur_historique' => $utilisateurHistorique],
            );
            $annulations = $connexion->executeStatement(
                "UPDATE campement.mouvement_stock
                 SET annule_par_id = :utilisateur_historique
                 WHERE annule_par_id IN (
                     SELECT id
                     FROM campement.utilisateur
                     WHERE actif = FALSE
                       AND desactive_at <= CURRENT_TIMESTAMP - INTERVAL '1 month'
                 )",
                ['utilisateur_historique' => $utilisateurHistorique],
            );
            $comptes = $connexion->executeStatement(
                "DELETE FROM campement.utilisateur
                 WHERE actif = FALSE
                   AND desactive_at <= CURRENT_TIMESTAMP - INTERVAL '1 month'
                   AND id <> :utilisateur_historique",
                ['utilisateur_historique' => $utilisateurHistorique],
            );

            return [
                'situations' => $situations,
                'mouvements' => $mouvements,
                'annulations' => $annulations,
                'comptes' => $comptes,
            ];
        });

        $output->writeln(sprintf(
            '<info>Purge terminée : %d situation(s), %d mouvement(s) et %d annulation(s) réattribué(s), %d compte(s).</info>',
            $resultats['situations'],
            $resultats['mouvements'],
            $resultats['annulations'],
            $resultats['comptes'],
        ));

        return Command::SUCCESS;
    }
}
