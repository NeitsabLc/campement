<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\StockageDocumentParticipant;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:documents:reconcilier', description: 'Détecte les documents manquants et supprime prudemment les fichiers orphelins.')]
final class ReconcilierDocumentsParticipantsCommand extends Command
{
    public function __construct(
        private readonly Connection $connexion,
        private readonly StockageDocumentParticipant $stockage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('supprimer', null, InputOption::VALUE_NONE, 'Supprime les fichiers orphelins assez anciens.')
            ->addOption('anciennete-heures', null, InputOption::VALUE_REQUIRED, 'Délai de sécurité avant suppression.', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $anciennete = filter_var($input->getOption('anciennete-heures'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $anciennete) {
            $output->writeln('<error>L’ancienneté doit être un nombre entier d’heures supérieur ou égal à 1.</error>');

            return Command::INVALID;
        }

        $references = array_values(array_filter(
            $this->connexion->fetchFirstColumn('SELECT chemin_stockage FROM campement.document_participant'),
            static fn (mixed $nom): bool => is_string($nom) && '' !== $nom,
        ));
        $referencesIndexees = array_fill_keys($references, true);
        $tousLesFichiers = $this->stockage->listerFichiers();
        $fichiersAnciens = $this->stockage->listerFichiers(new \DateTimeImmutable(sprintf('-%d hours', $anciennete)));

        $manquants = array_values(array_filter($references, fn (string $nom): bool => !in_array($nom, $tousLesFichiers, true)));
        $orphelins = array_values(array_filter($fichiersAnciens, static fn (string $nom): bool => !isset($referencesIndexees[$nom])));
        $supprimes = 0;
        if ($input->getOption('supprimer')) {
            foreach ($orphelins as $nom) {
                $this->stockage->supprimer($nom);
                if (!is_file($this->stockage->chemin($nom))) {
                    ++$supprimes;
                }
            }
        }

        $output->writeln(sprintf(
            '<info>Réconciliation terminée : %d référence(s) sans fichier, %d fichier(s) orphelin(s) ancien(s), %d supprimé(s).</info>',
            count($manquants),
            count($orphelins),
            $supprimes,
        ));
        if ([] !== $manquants) {
            $output->writeln('<comment>Des références BDD pointent vers un fichier absent ; une intervention manuelle est requise.</comment>');
        }

        return Command::SUCCESS;
    }
}
