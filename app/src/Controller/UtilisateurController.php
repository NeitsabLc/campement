<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\SejourRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class UtilisateurController extends AbstractController
{
    private const ROLES = [
        Utilisateur::ROLE_ADMIN => 'Administrateur',
        Utilisateur::ROLE_GESTIONNAIRE => 'Gestionnaire',
        Utilisateur::ROLE_GROUPE => 'Groupe',
    ];

    #[Route('/utilisateurs', name: 'app_utilisateurs', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UtilisateurRepository $utilisateurs,
        SejourRepository $sejours,
        GroupeRepository $groupes,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
    ): Response {
        $connecte = $this->getUser();
        if (!$connecte instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }
        $estAdministrateur = $this->isGranted(Utilisateur::ROLE_ADMIN);
        $sejoursAccessibles = $estAdministrateur
            ? $sejours->findBy([], ['dateDebut' => 'DESC'])
            : $connecte->getSejoursGeres()->toArray();
        $sejourSelectionne = $estAdministrateur ? null : $this->selectionnerSejour($request, $sejoursAccessibles);
        $rolesAccessibles = $estAdministrateur
            ? self::ROLES
            : array_intersect_key(self::ROLES, array_flip([Utilisateur::ROLE_GESTIONNAIRE, Utilisateur::ROLE_GROUPE]));

        $donnees = [
            'utilisateur_id' => $request->request->getString('utilisateur_id'),
            'prenom' => trim($request->request->getString('prenom')),
            'nom' => trim($request->request->getString('nom')),
            'email' => mb_strtolower(trim($request->request->getString('email'))),
            'role' => $request->request->getString('role'),
            'sejours' => $request->request->all('sejours'),
            'sejour_groupe' => $request->request->getString('sejour_groupe'),
            'groupe' => $request->request->getString('groupe'),
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('creer_utilisateur', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $utilisateurModifie = null;
            if ('' !== $donnees['utilisateur_id']) {
                $utilisateurModifie = Uuid::isValid($donnees['utilisateur_id'])
                    ? $utilisateurs->find($donnees['utilisateur_id'])
                    : null;
                if (!$utilisateurModifie instanceof Utilisateur
                    || !$this->utilisateurEstVisible($utilisateurModifie, $sejourSelectionne, $estAdministrateur)) {
                    throw $this->createAccessDeniedException('Cet utilisateur ne peut pas être modifié dans votre périmètre.');
                }
            }

            if ('' === $donnees['prenom'] || mb_strlen($donnees['prenom']) > 100) {
                $erreurs[] = 'Le prénom est obligatoire et limité à 100 caractères.';
            }
            if ('' === $donnees['nom'] || mb_strlen($donnees['nom']) > 100) {
                $erreurs[] = 'Le nom est obligatoire et limité à 100 caractères.';
            }
            if (false === filter_var($donnees['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($donnees['email']) > 180) {
                $erreurs[] = 'Saisissez une adresse électronique valide.';
            } elseif (($utilisateurAvecEmail = $utilisateurs->findOneBy(['email' => $donnees['email']])) instanceof Utilisateur
                && $utilisateurAvecEmail !== $utilisateurModifie) {
                $erreurs[] = 'Un utilisateur possède déjà cette adresse électronique.';
            }
            if (!isset($rolesAccessibles[$donnees['role']])) {
                $erreurs[] = 'Sélectionnez un rôle valide.';
            }

            $sejoursChoisis = [];
            if (Utilisateur::ROLE_GESTIONNAIRE === $donnees['role']) {
                if (!$estAdministrateur && null !== $sejourSelectionne) {
                    $sejoursChoisis[] = $sejourSelectionne;
                } else {
                    foreach (array_unique($donnees['sejours']) as $id) {
                        $sejour = is_string($id) ? $sejours->find($id) : null;
                        if ($sejour instanceof Sejour && $this->contientSejour($sejoursAccessibles, $sejour)) {
                            $sejoursChoisis[] = $sejour;
                        }
                    }
                }
                if ([] === $sejoursChoisis) {
                    $erreurs[] = 'Un gestionnaire doit être associé à au moins un séjour.';
                }
            }

            $groupeChoisi = null;
            if (Utilisateur::ROLE_GROUPE === $donnees['role']) {
                $sejourDuGroupe = Uuid::isValid($donnees['sejour_groupe'])
                    ? $sejours->find($donnees['sejour_groupe'])
                    : null;
                $groupeChoisi = Uuid::isValid($donnees['groupe'])
                    ? $groupes->find($donnees['groupe'])
                    : null;
                if (!$sejourDuGroupe instanceof Sejour
                    || !$this->contientSejour($sejoursAccessibles, $sejourDuGroupe)) {
                    $erreurs[] = 'Sélectionnez un séjour valide pour cet utilisateur groupe.';
                } elseif (!$groupeChoisi instanceof Groupe
                    || $groupeChoisi->getSejour() !== $sejourDuGroupe
                    || !$this->contientSejour($sejoursAccessibles, $groupeChoisi->getSejour())
                    || (null !== $sejourSelectionne && $groupeChoisi->getSejour() !== $sejourSelectionne)) {
                    $erreurs[] = 'Sélectionnez un groupe appartenant au séjour choisi.';
                    $groupeChoisi = null;
                }
            }

            if ([] === $erreurs) {
                $creation = !$utilisateurModifie instanceof Utilisateur;
                $utilisateur = $utilisateurModifie ?? new Utilisateur();
                foreach ($utilisateur->getSejoursGeres()->toArray() as $ancienSejour) {
                    $utilisateur->removeSejourGere($ancienSejour);
                }
                $utilisateur
                    ->setPrenom($donnees['prenom'])
                    ->setNom($donnees['nom'])
                    ->setEmail($donnees['email'])
                    ->setRole($donnees['role'])
                    ->setGroupe($groupeChoisi);
                foreach ($sejoursChoisis as $sejour) {
                    $utilisateur->addSejourGere($sejour);
                }
                if ($creation) {
                    $motDePasseProvisoire = $this->genererMotDePasseProvisoire();
                    $utilisateur
                        ->setPassword($hasher->hashPassword($utilisateur, $motDePasseProvisoire))
                        ->setChangementMotDePasseRequis(true);
                    $entityManager->persist($utilisateur);
                    $entityManager->flush();

                    try {
                        $mailer->send((new TemplatedEmail())
                            ->from('no-reply@campement.local')
                            ->to($utilisateur->getEmail())
                            ->subject('Votre accès à Campement')
                            ->htmlTemplate('emails/nouvel_utilisateur.html.twig')
                            ->context(['utilisateur' => $utilisateur, 'mot_de_passe' => $motDePasseProvisoire]));
                    } catch (\Throwable $exception) {
                        $entityManager->remove($utilisateur);
                        $entityManager->flush();
                        throw $exception;
                    }
                } else {
                    $entityManager->flush();
                }

                $this->addFlash('success', sprintf(
                    $creation
                        ? 'Le compte de %s %s a été créé et son mot de passe provisoire lui a été envoyé.'
                        : 'Le compte de %s %s a été mis à jour.',
                    $utilisateur->getPrenom(),
                    $utilisateur->getNom(),
                ));

                return $this->redirectToRoute('app_utilisateurs', null === $sejourSelectionne ? [] : ['sejour' => $sejourSelectionne->getId()]);
            }
        }

        $utilisateursVisibles = array_values(array_filter(
            $utilisateurs->findPourAdministration(),
            fn (Utilisateur $utilisateur): bool => $this->utilisateurEstVisible(
                $utilisateur,
                $sejourSelectionne,
                $estAdministrateur,
            ),
        ));
        $groupesAccessibles = $estAdministrateur
            ? $groupes->findBy(['actif' => true], ['nom' => 'ASC'])
            : (null === $sejourSelectionne ? [] : $groupes->findPourSejour($sejourSelectionne));

        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateursVisibles,
            'sejours' => $sejoursAccessibles,
            'sejour_selectionne' => $sejourSelectionne,
            'groupes' => $groupesAccessibles,
            'roles' => $rolesAccessibles,
            'est_administrateur' => $estAdministrateur,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
        ]);
    }

    #[Route('/utilisateurs/{id}/statut', name: 'app_utilisateurs_statut', methods: ['POST'])]
    public function changerStatut(
        Utilisateur $utilisateur,
        Request $request,
        SejourRepository $sejours,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('statut_utilisateur_'.$utilisateur->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $connecte = $this->getUser();
        $sejourSelectionne = $request->query->has('sejour') ? $sejours->find($request->query->getString('sejour')) : null;
        $estAdministrateur = $this->isGranted(Utilisateur::ROLE_ADMIN);
        if (!$estAdministrateur && (!$connecte instanceof Utilisateur
            || !$sejourSelectionne instanceof Sejour
            || !$connecte->getSejoursGeres()->contains($sejourSelectionne)
            || !$this->utilisateurEstVisible($utilisateur, $sejourSelectionne, false))) {
            throw $this->createAccessDeniedException('Cet utilisateur n’appartient pas au séjour sélectionné.');
        }
        if ($utilisateur === $connecte) {
            $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        } else {
            $utilisateur->setActif(!$utilisateur->isActif());
            $entityManager->flush();
            $this->addFlash('success', sprintf('Le compte a été %s.', $utilisateur->isActif() ? 'réactivé' : 'désactivé'));
        }

        return $this->redirectToRoute('app_utilisateurs', null === $sejourSelectionne ? [] : ['sejour' => $sejourSelectionne->getId()]);
    }

    private function genererMotDePasseProvisoire(): string
    {
        return 'Ca!'.substr(strtr(base64_encode(random_bytes(12)), '+/', 'AZ'), 0, 16).'9';
    }

    /** @param list<Sejour> $sejoursAccessibles */
    private function selectionnerSejour(Request $request, array $sejoursAccessibles): ?Sejour
    {
        $idDemande = $request->query->getString('sejour');
        if ('' === $idDemande) {
            $idDemande = (string) $request->getSession()->get('utilisateurs_sejour', '');
        }
        foreach ($sejoursAccessibles as $sejour) {
            if ((string) $sejour->getId() === $idDemande) {
                $request->getSession()->set('utilisateurs_sejour', $idDemande);
                return $sejour;
            }
        }
        $sejour = $sejoursAccessibles[0] ?? null;
        if ($sejour instanceof Sejour) {
            $request->getSession()->set('utilisateurs_sejour', (string) $sejour->getId());
        }

        return $sejour instanceof Sejour ? $sejour : null;
    }

    /** @param list<Sejour> $sejours */
    private function contientSejour(array $sejours, Sejour $recherche): bool
    {
        return array_any($sejours, static fn (Sejour $sejour): bool => $sejour === $recherche);
    }

    private function utilisateurEstVisible(Utilisateur $utilisateur, ?Sejour $sejour, bool $estAdministrateur): bool
    {
        if (Utilisateur::ROLE_TECHNIQUE === $utilisateur->getRole()) {
            return false;
        }
        if ($estAdministrateur) {
            return true;
        }
        if (null === $sejour) {
            return false;
        }

        return (Utilisateur::ROLE_GESTIONNAIRE === $utilisateur->getRole() && $utilisateur->getSejoursGeres()->contains($sejour))
            || (Utilisateur::ROLE_GROUPE === $utilisateur->getRole() && $utilisateur->getGroupe()?->getSejour() === $sejour);
    }
}
