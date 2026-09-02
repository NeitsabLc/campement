<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Sejour;
use App\Repository\SejourRepository;
use App\Service\AnonymisationSejour;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsCommand(name: 'app:sejours:anonymiser', description: 'Anonymise les séjours terminés depuis 48 heures et prévient leurs gestionnaires.')]
final class AnonymiserSejoursTerminesCommand extends Command
{
    public function __construct(
        private readonly SejourRepository $sejours,
        private readonly AnonymisationSejour $anonymisation,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('preview-to', null, InputOption::VALUE_REQUIRED, 'Envoie un aperçu sans anonymiser de séjour.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apercuDestinataire = $input->getOption('preview-to');
        if (is_string($apercuDestinataire) && '' !== $apercuDestinataire) {
            $sejour = $this->sejours->findOneBy([], ['dateFin' => 'DESC']);
            if (!$sejour instanceof Sejour) {
                $output->writeln('<error>Aucun séjour disponible pour générer l’aperçu.</error>');

                return Command::FAILURE;
            }
            $this->prevenir($sejour, $apercuDestinataire);
            $output->writeln(sprintf('<info>Aperçu envoyé à %s.</info>', $apercuDestinataire));

            return Command::SUCCESS;
        }

        $limite = new \DateTimeImmutable('today -2 days');
        foreach ($this->sejours->findAAnonymiser($limite) as $sejour) {
            $this->prevenir($sejour);
            $this->anonymisation->anonymiser($sejour);
            $output->writeln(sprintf('<info>%s anonymisé.</info>', $sejour->getNom()));
        }

        return Command::SUCCESS;
    }

    private function prevenir(Sejour $sejour, ?string $apercuDestinataire = null): void
    {
        $destinataires = null !== $apercuDestinataire ? [new Address($apercuDestinataire)] : [];
        if (null === $apercuDestinataire) {
            foreach ($sejour->getGestionnaires() as $gestionnaire) {
                if ($gestionnaire->isActif()) {
                    $destinataires[] = new Address($gestionnaire->getEmail(), $gestionnaire->getPrenom().' '.$gestionnaire->getNom());
                }
            }
        }
        if ([] === $destinataires) {
            return;
        }
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to(...$destinataires)
            ->subject((null !== $apercuDestinataire ? '[APERÇU] ' : '').'Anonymisation du séjour « '.$sejour->getNom().' »')
            ->htmlTemplate('emails/anonymisation_sejour.html.twig')
            ->context(['sejour' => $sejour]);
        $this->mailer->send($email);
    }
}
