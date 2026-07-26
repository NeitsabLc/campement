<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Fournisseur;
use App\Entity\Utilisateur;
use App\Repository\FournisseurRepository;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class FournisseurController extends AbstractController
{
    #[Route('/fournisseurs', name: 'app_fournisseurs', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContexteSejour $sejours,
        FournisseurRepository $fournisseurs,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $sejours->actif();
        $afficherInactifs = $request->query->getBoolean('inactifs');
        $donnees = [
            'fournisseur_id' => $request->request->getString('fournisseur_id'),
            'nom' => trim($request->request->getString('nom')),
            'telephone' => trim($request->request->getString('telephone')),
            'email' => trim($request->request->getString('email')),
            'adresse' => trim($request->request->getString('adresse')),
        ];
        $erreurs = [];

        if ($request->isMethod('POST') && null !== $sejour) {
            if (!$this->isCsrfTokenValid('enregistrer_fournisseur', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $fournisseur = null;
            if ('' !== $donnees['fournisseur_id']) {
                $fournisseur = Uuid::isValid($donnees['fournisseur_id'])
                    ? $fournisseurs->find($donnees['fournisseur_id'])
                    : null;
                if (null === $fournisseur || $fournisseur->getSejour() !== $sejour) {
                    $fournisseur = null;
                    $erreurs[] = 'Le fournisseur à modifier est introuvable pour ce séjour.';
                }
            }

            if ('' === $donnees['nom']) {
                $erreurs[] = 'Le nom du fournisseur est obligatoire.';
            } elseif (mb_strlen($donnees['nom']) > 150) {
                $erreurs[] = 'Le nom du fournisseur ne peut pas dépasser 150 caractères.';
            } elseif ($fournisseurs->existeAvecNomPourSejour($sejour, $donnees['nom'], $fournisseur)) {
                $erreurs[] = 'Un fournisseur portant ce nom existe déjà pour ce séjour.';
            }
            if (mb_strlen($donnees['telephone']) > 30) {
                $erreurs[] = 'Le numéro de téléphone ne peut pas dépasser 30 caractères.';
            }
            if (mb_strlen($donnees['email']) > 150) {
                $erreurs[] = 'L’adresse e-mail ne peut pas dépasser 150 caractères.';
            } elseif ('' !== $donnees['email'] && false === filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) {
                $erreurs[] = 'L’adresse e-mail n’est pas valide.';
            }

            if ([] === $erreurs) {
                $creation = null === $fournisseur;
                $fournisseur ??= new Fournisseur($sejour, $donnees['nom']);
                $fournisseur
                    ->setNom($donnees['nom'])
                    ->setTelephone('' === $donnees['telephone'] ? null : $donnees['telephone'])
                    ->setEmail('' === $donnees['email'] ? null : $donnees['email'])
                    ->setAdresse('' === $donnees['adresse'] ? null : $donnees['adresse']);
                if ($creation) {
                    $entityManager->persist($fournisseur);
                }
                $entityManager->flush();
                $this->addFlash('success', sprintf(
                    'Le fournisseur « %s » a bien été %s.',
                    $fournisseur->getNom(),
                    $creation ? 'créé' : 'modifié',
                ));

                return $this->redirectToRoute('app_fournisseurs');
            }
        }

        return $this->render('fournisseur/index.html.twig', [
            'sejour' => $sejour,
            'fournisseurs' => null === $sejour ? [] : $fournisseurs->findPourSejour($sejour, $afficherInactifs),
            'afficher_inactifs' => $afficherInactifs,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
        ]);
    }

    #[Route('/fournisseurs/{id}/desactiver', name: 'app_fournisseur_desactiver', methods: ['POST'])]
    public function desactiver(
        string $id,
        Request $request,
        ContexteSejour $sejours,
        FournisseurRepository $fournisseurs,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $sejours->actif();
        $fournisseur = Uuid::isValid($id) ? $fournisseurs->find($id) : null;
        if (null === $sejour || null === $fournisseur || $fournisseur->getSejour() !== $sejour) {
            throw $this->createNotFoundException('Fournisseur introuvable pour le séjour actif.');
        }
        if (!$this->isCsrfTokenValid('desactiver_fournisseur_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $fournisseur->setActif(false);
        $entityManager->flush();
        $this->addFlash('success', sprintf('Le fournisseur « %s » a bien été désactivé.', $fournisseur->getNom()));

        return $this->redirectToRoute('app_fournisseurs');
    }

    #[Route('/fournisseurs/{id}/reactiver', name: 'app_fournisseur_reactiver', methods: ['POST'])]
    public function reactiver(
        string $id,
        Request $request,
        ContexteSejour $sejours,
        FournisseurRepository $fournisseurs,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $sejours->actif();
        $fournisseur = Uuid::isValid($id) ? $fournisseurs->find($id) : null;
        if (null === $sejour || null === $fournisseur || $fournisseur->getSejour() !== $sejour) {
            throw $this->createNotFoundException('Fournisseur introuvable pour le séjour actif.');
        }
        if (!$this->isCsrfTokenValid('reactiver_fournisseur_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $fournisseur->setActif(true);
        $entityManager->flush();
        $this->addFlash('success', sprintf('Le fournisseur « %s » a bien été réactivé.', $fournisseur->getNom()));

        return $this->redirectToRoute('app_fournisseurs', ['inactifs' => 1]);
    }
}
