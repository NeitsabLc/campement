<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class MotDePasseController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(APP_PUBLIC_URL)%')]
        private readonly string $urlPublique,
        #[Autowire('%env(MAILER_FROM_EMAIL)%')]
        private readonly string $emailExpediteur,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private readonly string $nomExpediteur,
    ) {
    }

    #[Route('/mot-de-passe-oublie', name: 'app_mot_de_passe_oublie', methods: ['GET', 'POST'])]
    #[RateLimit('password_reset_request', methods: ['POST'])]
    public function demander(
        Request $request,
        UtilisateurRepository $utilisateurs,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
    ): Response {
        $envoye = false;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mot_de_passe_oublie', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $utilisateur = $utilisateurs->loadUserByIdentifier($request->request->getString('email'));
            if ($utilisateur instanceof Utilisateur && $utilisateur->isActif()) {
                $jeton = bin2hex(random_bytes(32));
                $utilisateur->definirJetonReinitialisation($jeton, new \DateTimeImmutable('+1 hour'));
                $entityManager->flush();

                $lienReinitialisation = rtrim($this->urlPublique, '/').$this->generateUrl(
                    'app_reinitialiser_mot_de_passe',
                    ['jeton' => $jeton],
                );

                $mailer->send((new TemplatedEmail())
                    ->from(new Address($this->emailExpediteur, $this->nomExpediteur))
                    ->to($utilisateur->getEmail())
                    ->subject('Réinitialisation de votre mot de passe Campement')
                    ->htmlTemplate('emails/reinitialisation_mot_de_passe.html.twig')
                    ->context(['utilisateur' => $utilisateur, 'lien_reinitialisation' => $lienReinitialisation]));
            }
            $envoye = true;
        }

        return $this->render('securite/mot_de_passe_oublie.html.twig', ['envoye' => $envoye]);
    }

    #[Route('/reinitialiser-mot-de-passe/{jeton}', name: 'app_reinitialiser_mot_de_passe', methods: ['GET', 'POST'])]
    #[RateLimit('password_reset_submit', methods: ['POST'])]
    public function reinitialiser(
        string $jeton,
        Request $request,
        UtilisateurRepository $utilisateurs,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $utilisateur = $utilisateurs->findOneByJetonReinitialisation($jeton);
        $utilisateurConnecte = $this->getUser();
        if ($utilisateurConnecte instanceof Utilisateur && $utilisateurConnecte !== $utilisateur) {
            $this->addFlash('error_auto_dismiss', 'Ce lien est associé à un autre compte. Déconnectez-vous avant de l’utiliser.');

            return $this->redirectToRoute('app_tableau_de_bord');
        }
        if (!$utilisateur instanceof Utilisateur || !$utilisateur->jetonReinitialisationEstValide($jeton)) {
            return $this->render('securite/reinitialiser_mot_de_passe.html.twig', ['jeton_valide' => false, 'erreurs' => []]);
        }

        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reinitialiser_mot_de_passe_'.$jeton, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $motDePasse = $request->request->getString('mot_de_passe');
            $confirmation = $request->request->getString('confirmation');
            $erreurs = $this->validerMotDePasse($motDePasse, $confirmation);
            if ([] === $erreurs) {
                $utilisateur
                    ->setPassword($hasher->hashPassword($utilisateur, $motDePasse))
                    ->setChangementMotDePasseRequis(false)
                    ->effacerJetonReinitialisation();
                $entityManager->flush();
                $this->addFlash('success', 'Votre mot de passe a été mis à jour. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_connexion');
            }
        }

        return $this->render('securite/reinitialiser_mot_de_passe.html.twig', ['jeton_valide' => true, 'erreurs' => $erreurs]);
    }

    #[Route('/modifier-mon-mot-de-passe', name: 'app_modifier_mot_de_passe', methods: ['GET', 'POST'])]
    public function modifier(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return $this->redirectToRoute('app_connexion');
        }

        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier_mot_de_passe', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $motDePasse = $request->request->getString('mot_de_passe');
            $erreurs = $this->validerMotDePasse($motDePasse, $request->request->getString('confirmation'));
            if (!$utilisateur->isChangementMotDePasseRequis()
                && !$hasher->isPasswordValid($utilisateur, $request->request->getString('mot_de_passe_actuel'))) {
                array_unshift($erreurs, 'Le mot de passe actuel est incorrect.');
            }
            if ([] === $erreurs) {
                $utilisateur
                    ->setPassword($hasher->hashPassword($utilisateur, $motDePasse))
                    ->setChangementMotDePasseRequis(false)
                    ->effacerJetonReinitialisation();
                $entityManager->flush();
                $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

                return $this->redirectToRoute('app_tableau_de_bord');
            }
        }

        return $this->render('securite/modifier_mot_de_passe.html.twig', [
            'premiere_connexion' => $utilisateur->isChangementMotDePasseRequis(),
            'erreurs' => $erreurs,
        ]);
    }

    /** @return list<string> */
    private function validerMotDePasse(string $motDePasse, string $confirmation): array
    {
        $erreurs = [];
        if (mb_strlen($motDePasse) < 12
            || !preg_match('/[a-z]/', $motDePasse)
            || !preg_match('/[A-Z]/', $motDePasse)
            || !preg_match('/\d/', $motDePasse)
            || !preg_match('/[^a-zA-Z0-9]/', $motDePasse)) {
            $erreurs[] = 'Le mot de passe doit contenir au moins 12 caractères, une minuscule, une majuscule, un chiffre et un caractère spécial.';
        }
        if ($motDePasse !== $confirmation) {
            $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
        }

        return $erreurs;
    }
}
