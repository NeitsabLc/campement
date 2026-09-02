<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use App\Entity\Utilisateur;
use App\Repository\PublicCibleRepository;
use App\Repository\SejourRepository;
use App\Repository\TypeRepasRepository;
use App\Service\AnonymisationSejour;
use App\Service\ContexteSejour;
use App\Service\DuplicationSejour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class SejourController extends AbstractController
{
    #[Route('/sejours', name: 'app_sejours', methods: ['GET', 'POST'])]
    #[Route('/sejours/ajouter', name: 'app_sejour_ajouter', methods: ['GET', 'POST'])]
    #[Route('/sejours/{id}/modifier', name: 'app_sejour_modifier', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SejourRepository $repository,
        PublicCibleRepository $publics,
        TypeRepasRepository $typesRepas,
        ContexteSejour $contexte,
        EntityManagerInterface $entityManager,
        DuplicationSejour $duplication,
        ?string $id = null,
    ): Response {
        $admin = $this->isGranted(Utilisateur::ROLE_ADMIN);
        $connecte = $this->getUser();
        if (!$connecte instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }
        if ('app_sejour_ajouter' === $request->attributes->get('_route') && !$admin) {
            throw $this->createAccessDeniedException('Seul un administrateur peut créer un séjour.');
        }
        $liste = $admin ? $repository->findBy([], ['dateDebut' => 'DESC']) : $contexte->accessibles();
        $erreurs = [];
        $idFormulaire = $request->request->getString('sejour_id', $id ?? '');
        $sejour = '' !== $idFormulaire && Uuid::isValid($idFormulaire) ? $repository->find($idFormulaire) : null;
        $sourceId = $request->request->getString('source_id');
        $sourceDuplication = '' !== $sourceId && Uuid::isValid($sourceId) ? $repository->find($sourceId) : null;
        if (null !== $id && (!$sejour instanceof Sejour || (!$admin && !$connecte->getSejoursGeres()->contains($sejour)))) {
            throw $this->createNotFoundException('Séjour introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_sejour', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $creation = !$sejour instanceof Sejour;
            if (($creation && !$admin) || (!$creation && !$admin && !$connecte->getSejoursGeres()->contains($sejour))) {
                throw $this->createAccessDeniedException('Ce séjour ne peut pas être modifié.');
            }
            $nom = trim($request->request->getString('nom'));
            $datesValides = true;
            try {
                $debut = new \DateTimeImmutable($request->request->getString('date_debut'));
                $fin = new \DateTimeImmutable($request->request->getString('date_fin'));
            } catch (\Throwable) {
                $datesValides = false;
                $debut = $fin = new \DateTimeImmutable();
            }
            $publicIds = array_filter($request->request->all('publics'), 'is_string');
            if ('' === $nom || mb_strlen($nom) > 150) {
                $erreurs[] = 'Le nom est obligatoire et limité à 150 caractères.';
            }
            if (!$datesValides || $fin < $debut) {
                $erreurs[] = 'Les dates du séjour sont invalides.';
            }
            if ([] === $publicIds) {
                $erreurs[] = 'Sélectionnez au moins un type de public.';
            }
            if ([] === $erreurs) {
                $sejour ??= new Sejour($nom, $debut, $fin);
                $sejour->setNom($nom)
                    ->setModuleIntendanceActif($request->request->has('module_intendance'))
                    ->setModuleAdministratifActif($request->request->has('module_administratif'))
                    ->setModuleSituationsParticulieresActif($request->request->has('module_situations_particulieres'));
                if (!$creation) {
                    $sejour->setDates($debut, $fin);
                }
                foreach ($publics->findActifs() as $public) {
                    in_array((string) $public->getId(), $publicIds, true) ? $sejour->addPublicCible($public) : $sejour->removePublicCible($public);
                }
                if ($creation) {
                    $entityManager->persist($sejour);
                    foreach ($typesRepas->findActifs() as $typeRepas) {
                        $entityManager->persist(new SejourTypeRepas($sejour, $typeRepas, $typeRepas->getOrdre()));
                    }
                }
                if ($creation && $sourceDuplication instanceof Sejour) {
                    $choix = array_values(array_filter($request->request->all('duplication'), 'is_string'));
                    $entityManager->wrapInTransaction(function () use ($entityManager, $duplication, $sourceDuplication, $sejour, $choix, $connecte): void {
                        $entityManager->flush();
                        $duplication->dupliquer($sourceDuplication, $sejour, $choix, $connecte);
                    });
                } else {
                    $entityManager->flush();
                }
                $this->addFlash('success', $creation ? 'Le séjour a été créé.' : 'Le séjour a été mis à jour.');

                return $this->redirectToRoute('app_sejours');
            }
        }

        $vue = 'app_sejours' === $request->attributes->get('_route') && !$request->isMethod('POST')
            ? 'sejour/index.html.twig'
            : 'sejour/formulaire.html.twig';

        return $this->render($vue, [
            'sejours' => $liste, 'publics' => $publics->findActifs(), 'erreurs' => $erreurs,
            'sejour_selectionne' => $contexte->actif(),
            'est_administrateur' => $admin,
            'sejour_formulaire' => $sejour,
            'source_duplication' => $sourceDuplication,
        ]);
    }

    #[Route('/sejours/{id}/dupliquer', name: 'app_sejour_dupliquer', methods: ['GET'])]
    #[IsGranted(Utilisateur::ROLE_ADMIN)]
    public function dupliquer(Sejour $sejour, PublicCibleRepository $publics): Response
    {
        return $this->render('sejour/formulaire.html.twig', [
            'sejours' => [], 'publics' => $publics->findActifs(), 'erreurs' => [],
            'sejour_formulaire' => null, 'source_duplication' => $sejour,
            'est_administrateur' => true, 'sejour_selectionne' => null,
        ]);
    }

    #[Route('/sejours/{id}/selection', name: 'app_sejour_selectionner', methods: ['POST'])]
    public function selectionner(Sejour $sejour, Request $request, ContexteSejour $contexte): Response
    {
        if (!$this->isCsrfTokenValid('selectionner_sejour_'.$sejour->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $contexte->selectionner($sejour);
        } catch (\InvalidArgumentException) {
            throw $this->createAccessDeniedException();
        }

        return $this->redirectToRoute('sejours' === $request->request->getString('retour') ? 'app_sejours' : 'app_tableau_de_bord');
    }

    #[Route('/sejours/{id}/statut', name: 'app_sejour_statut', methods: ['POST'])]
    public function statut(Sejour $sejour, Request $request, EntityManagerInterface $entityManager, ContexteSejour $contexte, AnonymisationSejour $anonymisation): Response
    {
        $connecte = $this->getUser();
        if (!$connecte instanceof Utilisateur || (!$this->isGranted(Utilisateur::ROLE_ADMIN) && !$connecte->getSejoursGeres()->contains($sejour))) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('statut_sejour_'.$sejour->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$sejour->isActif() && !$this->isGranted(Utilisateur::ROLE_ADMIN)) {
            throw $this->createAccessDeniedException();
        }
        $activation = !$sejour->isActif();
        if (!$activation && 'CONFIRMER' !== $request->request->getString('confirmation')) {
            $this->addFlash('error', 'La confirmation de suppression des données personnelles est requise.');

            return $this->redirectToRoute('app_sejours');
        }
        $sejour->setActif($activation);
        if ($activation) {
            $sejour->reinitialiserAnonymisation();
            $sejour->renouvelerJetonDistributionPublique();
        } else {
            $anonymisation->anonymiser($sejour, true);
        }
        if (!$sejour->isActif() && $contexte->actif() === $sejour) {
            $contexte->selectionner(null);
        }
        $entityManager->flush();
        $this->addFlash('success', $sejour->isActif() ? 'Le séjour a été réactivé.' : 'Le séjour a été désactivé.');

        return $this->redirectToRoute('app_sejours');
    }
}
