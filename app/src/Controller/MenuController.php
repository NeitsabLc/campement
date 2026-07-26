<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\Utilisateur;
use App\Repository\SejourPublicCibleRepository;
use App\Repository\DenreeRepository;
use App\Repository\MenuRepository;
use App\Service\ContexteSejour;
use App\Repository\SejourTypeRepasRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class MenuController extends AbstractController
{
    #[Route('/menus', name: 'app_menus', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContexteSejour $sejourRepository,
        SejourTypeRepasRepository $repasRepository,
        MenuRepository $menuRepository,
        DenreeRepository $denreeRepository,
        SejourPublicCibleRepository $publicCibleRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $sejourRepository->actif();

        if (null === $sejour) {
            return $this->render('menu/index.html.twig', ['sejour' => null]);
        }

        $repas = $repasRepository->findActifsPourSejour($sejour);
        if ([] === $repas) {
            return $this->render('menu/index.html.twig', ['sejour' => $sejour, 'repas' => []]);
        }

        $dateSelectionnee = $this->dateSelectionnee($request, $sejour->getDateDebut(), $sejour->getDateFin());
        $repasSelectionne = $this->repasSelectionne($request->query->getString('repas'), $repas);
        $menu = $menuRepository->findPourRepas($sejour, $dateSelectionnee, $repasSelectionne);
        $publicsCibles = $publicCibleRepository->findActifsPourSejour($sejour);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_menu', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $lignes = $request->request->all('denrees');
            $composition = [];
            foreach ($lignes as $denreeId => $valeurs) {
                if (!Uuid::isValid((string) $denreeId) || !is_array($valeurs)) {
                    continue;
                }

                $denree = $denreeRepository->findOneBy(['id' => $denreeId, 'sejour' => $sejour]);
                if (null === $denree || !$denree->isActif()) {
                    continue;
                }

                $quantitesValidees = [];
                foreach ($publicsCibles as $publicCible) {
                    $brut = str_replace(',', '.', trim((string) ($valeurs['quantites'][(string) $publicCible->getId()] ?? '')));
                    if (!is_numeric($brut) || (float) $brut < 0) {
                        $this->addFlash('error', sprintf('Renseignez une quantité positive ou nulle pour %s.', $denree->getNom()));
                        $request->getSession()->set('menu_form_data', $lignes);

                        return $this->redirectToRoute('app_menus', [
                            'date' => $dateSelectionnee->format('Y-m-d'),
                            'repas' => (string) $repasSelectionne->getId(),
                        ]);
                    }

                    $quantitesValidees[(string) $publicCible->getId()] = number_format((float) $brut, 3, '.', '');
                }

                $composition[] = ['denree' => $denree, 'quantites' => $quantitesValidees];
            }

            $menu ??= (new Menu())
                ->setSejour($sejour)
                ->setSejourTypeRepas($repasSelectionne)
                ->setDateMenu($dateSelectionnee);

            $lignesExistantes = [];
            foreach ($menu->getDenrees() as $ligneExistante) {
                $lignesExistantes[(string) $ligneExistante->getDenree()->getId()] = $ligneExistante;
            }

            $denreesConservees = [];
            foreach ($composition as $ordre => $donneesLigne) {
                $denreeId = (string) $donneesLigne['denree']->getId();
                $denreesConservees[$denreeId] = true;
                $menuDenree = $lignesExistantes[$denreeId] ?? (new MenuDenree())->setDenree($donneesLigne['denree']);
                $menuDenree->setOrdre($ordre);

                $quantitesExistantes = [];
                foreach ($menuDenree->getQuantites() as $quantiteExistante) {
                    $quantitesExistantes[(string) $quantiteExistante->getSejourPublicCible()->getId()] = $quantiteExistante;
                }

                foreach ($publicsCibles as $publicCible) {
                    $publicCibleId = (string) $publicCible->getId();
                    $quantite = $quantitesExistantes[$publicCibleId]
                        ?? (new MenuDenreeQuantite())->setSejourPublicCible($publicCible);
                    $quantite->setQuantiteIndividuelle($donneesLigne['quantites'][$publicCibleId]);
                    $menuDenree->addQuantite($quantite);
                }

                $menu->addDenree($menuDenree);
            }

            foreach ($lignesExistantes as $denreeId => $ligneExistante) {
                if (!isset($denreesConservees[$denreeId])) {
                    $menu->removeDenree($ligneExistante);
                    $entityManager->remove($ligneExistante);
                }
            }

            $entityManager->persist($menu);
            $entityManager->flush();
            $this->addFlash('success', 'Le repas a bien été enregistré.');

            return $this->redirectToRoute('app_menus', [
                'date' => $dateSelectionnee->format('Y-m-d'),
                'repas' => (string) $repasSelectionne->getId(),
            ]);
        }

        $menusExistants = [];
        foreach ($menuRepository->findActifsPourSejour($sejour) as $menuExistant) {
            $menusExistants[$menuExistant->getDateMenu()->format('Y-m-d').'|'.$menuExistant->getSejourTypeRepas()->getId()] = true;
        }

        $lignesFormulaire = [];
        $donneesSoumises = $request->getSession()->remove('menu_form_data');
        if (is_array($donneesSoumises)) {
            foreach ($donneesSoumises as $denreeId => $valeurs) {
                if (!Uuid::isValid((string) $denreeId) || !is_array($valeurs)) {
                    continue;
                }

                $denree = $denreeRepository->findOneBy(['id' => $denreeId, 'sejour' => $sejour]);
                if (null !== $denree && $denree->isActif()) {
                    $lignesFormulaire[] = [
                        'denree' => $denree,
                        'quantites' => is_array($valeurs['quantites'] ?? null) ? $valeurs['quantites'] : [],
                    ];
                }
            }
        } elseif (null !== $menu) {
            foreach ($menu->getDenrees() as $ligne) {
                $quantites = [];
                foreach ($ligne->getQuantites() as $quantite) {
                    $quantites[(string) $quantite->getSejourPublicCible()->getId()] = $quantite->getQuantiteIndividuelle();
                }
                $lignesFormulaire[] = ['denree' => $ligne->getDenree(), 'quantites' => $quantites];
            }
        }

        return $this->render('menu/index.html.twig', [
            'sejour' => $sejour,
            'repas' => $repas,
            'jours' => $this->joursDuSejour($sejour->getDateDebut(), $sejour->getDateFin()),
            'date_selectionnee' => $dateSelectionnee,
            'repas_selectionne' => $repasSelectionne,
            'menu' => $menu,
            'menus_existants' => $menusExistants,
            'denrees' => $denreeRepository->findActifsPourSejour($sejour),
            'publicsCibles' => $publicsCibles,
            'lignes_formulaire' => $lignesFormulaire,
        ]);
    }

    private function dateSelectionnee(Request $request, \DateTimeImmutable $debut, \DateTimeImmutable $fin): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('date')) ?: $debut;

        return $date < $debut || $date > $fin ? $debut : $date;
    }

    /** @param list<\App\Entity\SejourTypeRepas> $repas */
    private function repasSelectionne(string $id, array $repas): \App\Entity\SejourTypeRepas
    {
        foreach ($repas as $repasPossible) {
            if ((string) $repasPossible->getId() === $id) {
                return $repasPossible;
            }
        }

        return $repas[0];
    }

    /** @return list<array{date: \DateTimeImmutable, libelle: string}> */
    private function joursDuSejour(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $resultat = [];

        for ($date = $debut; $date <= $fin; $date = $date->modify('+1 day')) {
            $resultat[] = [
                'date' => $date,
                'libelle' => ucfirst($jours[(int) $date->format('w')]).' '.$date->format('j').' '.$mois[(int) $date->format('n')],
            ];
        }

        return $resultat;
    }
}
