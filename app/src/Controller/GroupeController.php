<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\SejourPublicCibleRepository;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class GroupeController extends AbstractController
{
    #[Route('/groupes', name: 'app_groupes', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContexteSejour $sejourRepository,
        GroupeRepository $groupeRepository,
        SejourPublicCibleRepository $publicCibleRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $sejourRepository->actif();
        $types = [];
        if (null !== $sejour) {
            foreach ($publicCibleRepository->findActifsPourSejour($sejour) as $configuration) {
                $public = $configuration->getPublicCible();
                $types[strtolower(str_replace('_', '-', $public->getCode()))] = $public->getLibelle();
            }
        }
        $afficherInactifs = $request->query->getBoolean('inactifs');
        $donnees = [
            'groupe_id' => $request->request->getString('groupe_id'),
            'nom' => trim($request->request->getString('nom')),
            'effectif_jeune' => $request->request->getString('effectif_jeune'),
            'effectif_adulte' => $request->request->getString('effectif_adulte'),
            'type' => $request->request->getString('type'),
        ];
        $erreurs = [];

        if ($request->isMethod('POST') && null !== $sejour) {
            if (!$this->isCsrfTokenValid('enregistrer_groupe', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $groupe = null;
            if ('' !== $donnees['groupe_id']) {
                if (Uuid::isValid($donnees['groupe_id'])) {
                    $groupePossible = $groupeRepository->find($donnees['groupe_id']);
                    if (null !== $groupePossible && $groupePossible->getSejour() === $sejour) {
                        $groupe = $groupePossible;
                    }
                }

                if (null === $groupe) {
                    $erreurs[] = 'L’unité participante à modifier est introuvable pour ce séjour.';
                }
            }

            if ('' === $donnees['nom']) {
                $erreurs[] = 'Le nom de l’unité est obligatoire.';
            } elseif (mb_strlen($donnees['nom']) > 150) {
                $erreurs[] = 'Le nom de l’unité ne peut pas dépasser 150 caractères.';
            } elseif ($groupeRepository->existeAvecNomPourSejour($sejour, $donnees['nom'], $groupe)) {
                $erreurs[] = 'Une unité participante portant ce nom existe déjà pour ce séjour.';
            }

            foreach (['effectif_jeune' => 'jeunes', 'effectif_adulte' => 'adultes'] as $champ => $libelle) {
                if (false === filter_var($donnees[$champ], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
                    $erreurs[] = sprintf('L’effectif %s doit être un nombre entier positif ou nul.', $libelle);
                }
            }

            if (!isset($types[$donnees['type']])) {
                $erreurs[] = 'Sélectionnez un type de public disponible pour ce séjour.';
            }

            if ([] === $erreurs) {
                $creation = null === $groupe;
                $groupe ??= (new Groupe())->setSejour($sejour);
                $groupe
                    ->setNom($donnees['nom'])
                    ->setEffectifJeune((int) $donnees['effectif_jeune'])
                    ->setEffectifAdulte((int) $donnees['effectif_adulte'])
                    ->setType($donnees['type']);
                if ($creation) {
                    $entityManager->persist($groupe);
                }
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'L’unité « %s » a bien été %s.',
                    $groupe->getNom(),
                    $creation ? 'créée' : 'modifiée',
                ));

                return $this->redirectToRoute('app_groupes');
            }
        }

        return $this->render('groupe/index.html.twig', [
            'sejour' => $sejour,
            'groupes' => null === $sejour ? [] : $groupeRepository->findPourSejour($sejour, $afficherInactifs),
            'afficher_inactifs' => $afficherInactifs,
            'types' => $types,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
        ]);
    }

    #[Route('/groupes/{id}/supprimer', name: 'app_groupes_supprimer', methods: ['POST'])]
    public function supprimer(
        string $id,
        Request $request,
        ContexteSejour $sejourRepository,
        GroupeRepository $groupeRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Unité participante introuvable.');
        }

        $sejour = $sejourRepository->actif();
        $groupe = $groupeRepository->find($id);
        if (null === $sejour || null === $groupe || (string) $groupe->getSejour()->getId() !== (string) $sejour->getId()) {
            throw $this->createNotFoundException('Unité participante introuvable pour le séjour actif.');
        }

        if (!$this->isCsrfTokenValid('supprimer_groupe_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $groupe->setActif(false);
        $entityManager->flush();
        $this->addFlash('success', sprintf('L’unité « %s » a bien été désactivée.', $groupe->getNom()));

        return $this->redirectToRoute('app_groupes');
    }
}
