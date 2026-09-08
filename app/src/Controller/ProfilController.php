<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilController extends AbstractController
{
    #[Route('/mon-profil', name: 'app_mon_profil', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UtilisateurRepository $utilisateurs,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return $this->redirectToRoute('app_connexion');
        }

        $formulaire = $request->request->getString('formulaire');
        $erreursInformations = [];
        $erreursMotDePasse = [];
        $donnees = [
            'prenom' => $utilisateur->getPrenom(),
            'nom' => $utilisateur->getNom(),
            'email' => $utilisateur->getEmail(),
        ];

        if ($request->isMethod('POST') && 'informations' === $formulaire) {
            if (!$this->isCsrfTokenValid('modifier_profil', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $donnees = [
                'prenom' => trim($request->request->getString('prenom')),
                'nom' => trim($request->request->getString('nom')),
                'email' => mb_strtolower(trim($request->request->getString('email'))),
            ];
            if ('' === $donnees['prenom'] || mb_strlen($donnees['prenom']) > 100) {
                $erreursInformations[] = 'Le prénom est obligatoire et limité à 100 caractères.';
            }
            if ('' === $donnees['nom'] || mb_strlen($donnees['nom']) > 100) {
                $erreursInformations[] = 'Le nom est obligatoire et limité à 100 caractères.';
            }
            if (false === filter_var($donnees['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($donnees['email']) > 180) {
                $erreursInformations[] = 'Saisissez une adresse électronique valide.';
            } elseif (($utilisateurAvecEmail = $utilisateurs->findOneBy(['email' => $donnees['email']])) instanceof Utilisateur
                && $utilisateurAvecEmail !== $utilisateur) {
                $erreursInformations[] = 'Un utilisateur possède déjà cette adresse électronique.';
            }

            if ([] === $erreursInformations) {
                $utilisateur
                    ->setPrenom($donnees['prenom'])
                    ->setNom($donnees['nom'])
                    ->setEmail($donnees['email']);
                $entityManager->flush();
                $this->addFlash('success', 'Vos informations ont été mises à jour.');

                return $this->redirectToRoute('app_mon_profil');
            }
        }

        if ($request->isMethod('POST') && 'mot_de_passe' === $formulaire) {
            if (!$this->isCsrfTokenValid('modifier_mot_de_passe_profil', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $motDePasse = $request->request->getString('mot_de_passe');
            if (!$hasher->isPasswordValid($utilisateur, $request->request->getString('mot_de_passe_actuel'))) {
                $erreursMotDePasse[] = 'Le mot de passe actuel est incorrect.';
            }
            if (mb_strlen($motDePasse) < 12
                || !preg_match('/[a-z]/', $motDePasse)
                || !preg_match('/[A-Z]/', $motDePasse)
                || !preg_match('/\d/', $motDePasse)
                || !preg_match('/[^a-zA-Z0-9]/', $motDePasse)) {
                $erreursMotDePasse[] = 'Le mot de passe doit contenir au moins 12 caractères, une minuscule, une majuscule, un chiffre et un caractère spécial.';
            }
            if ($motDePasse !== $request->request->getString('confirmation')) {
                $erreursMotDePasse[] = 'Les deux mots de passe ne correspondent pas.';
            }

            if ([] === $erreursMotDePasse) {
                $utilisateur
                    ->setPassword($hasher->hashPassword($utilisateur, $motDePasse))
                    ->effacerJetonReinitialisation();
                $entityManager->flush();
                $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

                return $this->redirectToRoute('app_mon_profil');
            }
        }

        return $this->render('utilisateur/profil.html.twig', [
            'donnees' => $donnees,
            'erreurs_informations' => $erreursInformations,
            'erreurs_mot_de_passe' => $erreursMotDePasse,
        ]);
    }
}
