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
    #[Route('/fournisseurs/ajouter', name: 'app_fournisseur_ajouter', methods: ['GET', 'POST'])]
    #[Route('/fournisseurs/{id}/modifier', name: 'app_fournisseur_modifier', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContexteSejour $sejours,
        FournisseurRepository $fournisseurs,
        EntityManagerInterface $entityManager,
        ?string $id = null,
    ): Response {
        $sejour = $sejours->actif();
        $afficherInactifs = $request->query->getBoolean('inactifs');
        $fournisseurRoute = null !== $id && Uuid::isValid($id) ? $fournisseurs->find($id) : null;
        if (null !== $id && (null === $sejour || null === $fournisseurRoute || $fournisseurRoute->getSejour() !== $sejour)) {
            throw $this->createNotFoundException('Fournisseur introuvable pour le séjour actif.');
        }
        $donnees = [
            'fournisseur_id' => $request->request->getString('fournisseur_id', $id ?? ''),
            'nom' => trim($request->request->getString('nom')),
            'telephone' => trim($request->request->getString('telephone')),
            'email' => trim($request->request->getString('email')),
            'adresse' => trim($request->request->getString('adresse')),
        ];
        if (!$request->isMethod('POST') && null !== $fournisseurRoute) {
            $donnees = [
                'fournisseur_id' => (string) $fournisseurRoute->getId(),
                'nom' => $fournisseurRoute->getNom(),
                'telephone' => $fournisseurRoute->getTelephone() ?? '',
                'email' => $fournisseurRoute->getEmail() ?? '',
                'adresse' => $fournisseurRoute->getAdresse() ?? '',
            ];
        }
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
            if (mb_strlen($donnees['adresse']) > Fournisseur::ADRESSE_LONGUEUR_MAX) {
                $erreurs[] = 'L’adresse ne peut pas dépasser 1 000 caractères.';
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

        $vue = 'app_fournisseurs' === $request->attributes->get('_route') && !$request->isMethod('POST')
            ? 'fournisseur/index.html.twig'
            : 'fournisseur/formulaire.html.twig';

        $listeFournisseurs = null === $sejour ? [] : $fournisseurs->findPourSejour($sejour, $afficherInactifs);
        if ($afficherInactifs) {
            $listeFournisseurs = array_values(array_filter(
                $listeFournisseurs,
                static fn (Fournisseur $fournisseur): bool => !$fournisseur->isActif(),
            ));
        }

        return $this->render($vue, [
            'sejour' => $sejour,
            'fournisseurs' => $listeFournisseurs,
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

        return $this->redirectToRoute('app_fournisseurs');
    }
}
