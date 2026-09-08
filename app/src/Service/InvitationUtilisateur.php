<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InvitationUtilisateur
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%env(APP_PUBLIC_URL)%')] private readonly string $urlPublique,
        #[Autowire('%env(MAILER_FROM_EMAIL)%')] private readonly string $emailExpediteur,
        #[Autowire('%env(MAILER_FROM_NAME)%')] private readonly string $nomExpediteur,
    ) {
    }

    public function envoyer(Utilisateur $utilisateur): void
    {
        $jeton = bin2hex(random_bytes(32));
        $utilisateur
            ->setPassword($this->hasher->hashPassword($utilisateur, bin2hex(random_bytes(32))))
            ->setChangementMotDePasseRequis(true)
            ->definirJetonReinitialisation($jeton, new \DateTimeImmutable('+24 hours'));
        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        $lien = rtrim($this->urlPublique, '/').$this->urls->generate(
            'app_reinitialiser_mot_de_passe',
            ['jeton' => $jeton],
        );
        try {
            $this->mailer->send((new TemplatedEmail())
                ->from(new Address($this->emailExpediteur, $this->nomExpediteur))
                ->to($utilisateur->getEmail())
                ->subject('Votre accès à Campement')
                ->htmlTemplate('emails/nouvel_utilisateur.html.twig')
                ->context(['utilisateur' => $utilisateur, 'lien_invitation' => $lien]));
        } catch (\Throwable $exception) {
            $this->entityManager->remove($utilisateur);
            $this->entityManager->flush();
            throw $exception;
        }
    }
}
