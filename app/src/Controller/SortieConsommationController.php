<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;
use App\Repository\OrigineMouvementRepository;
use App\Repository\SejourRepository;
use App\Repository\TypeMouvementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class SortieConsommationController extends AbstractController
{
    #[Route('/distribution/{jeton}', name: 'app_sortie_consommation', requirements: ['jeton' => '[0-9a-fA-F-]{36}'], methods: ['GET', 'POST'])]
    public function index(
        string $jeton,
        Request $request,
        SejourRepository $sejours,
        GroupeRepository $groupes,
        MenuRepository $menus,
        TypeMouvementRepository $types,
        OrigineMouvementRepository $origines,
        UtilisateurRepository $utilisateurs,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = Uuid::isValid($jeton) ? $sejours->findPourDistributionPublique($jeton) : null;
        if (null === $sejour) {
            return $this->render('sortie_consommation/index.html.twig', ['sejour' => null]);
        }

        $groupesActifs = $groupes->findActifsPourSejour($sejour);
        $menusActifs = array_values(array_filter(
            $menus->findActifsPourSejour($sejour),
            static fn ($menu): bool => $menu->getSejourTypeRepas()->isDistributionActive(),
        ));
        $selection = null;
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sortie_consommation', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $groupe = $this->entiteSelectionnee($request->request->getString('groupe'), $groupesActifs);
            $menu = $this->entiteSelectionnee($request->request->getString('menu'), $menusActifs);
            if (null === $groupe) $erreurs[] = 'Sélectionnez un groupe valide.';
            if (null === $menu) $erreurs[] = 'Sélectionnez un repas valide.';

            $quantites = [];
            if (null !== $menu) {
                $valeurs = $request->request->all('quantites');
                foreach ($menu->getDenrees() as $menuDenree) {
                    $id = (string) $menuDenree->getDenree()->getId();
                    $brut = str_replace([' ', ','], ['', '.'], trim((string) ($valeurs[$id] ?? '')));
                    if ('' === $brut || !is_numeric($brut) || (float) $brut < 0) {
                        $erreurs[] = sprintf('Renseignez une quantité positive ou nulle pour %s.', $menuDenree->getDenree()->getNom());
                        continue;
                    }
                    $quantites[$id] = number_format((float) $brut, 3, '.', '');
                }
                if ([] === array_filter($quantites, static fn (string $q): bool => (float) $q > 0)) {
                    $erreurs[] = 'Au moins une denrée doit avoir une quantité supérieure à zéro.';
                }
            }

            if ([] === $erreurs && null !== $groupe && null !== $menu) {
                $selection = ['groupe' => $groupe, 'menu' => $menu, 'quantites' => $quantites];
                if ('confirmer' === $request->request->getString('action')) {
                    $type = $types->findOneBy(['code' => 'SORTIE', 'actif' => true]);
                    $origine = $origines->findOneBy(['sejour' => $sejour, 'code' => 'DISTRIBUTION', 'actif' => true]);
                    $utilisateur = $utilisateurs->loadUserByIdentifier('saisie-consommation@campement.local');
                    if (null === $type || null === $origine || null === $utilisateur) {
                        throw new \RuntimeException('Le référentiel de sortie publique est incomplet.');
                    }

                    $mouvement = (new MouvementStock($sejour, $utilisateur, $type, $origine))
                        ->setGroupe($groupe)
                        ->setMenu($menu)
                        ->setDateMouvement($menu->getDateMenu());
                    $entityManager->persist($mouvement);
                    foreach ($menu->getDenrees() as $menuDenree) {
                        $quantite = $quantites[(string) $menuDenree->getDenree()->getId()];
                        if ((float) $quantite > 0) {
                            $entityManager->persist(new MouvementStockLigne($mouvement, $menuDenree->getDenree(), $quantite));
                        }
                    }
                    $entityManager->flush();

                    return $this->render('sortie_consommation/succes.html.twig', compact('groupe', 'menu', 'quantites'));
                }
            }
        }

        return $this->render('sortie_consommation/index.html.twig', [
            'sejour' => $sejour,
            'groupes' => $groupesActifs,
            'menus' => $menusActifs,
            'selection' => $selection,
            'erreurs' => $erreurs,
            'valeurs' => $request->request->all('quantites'),
            'groupe_soumis' => $request->request->getString('groupe'),
            'menu_soumis' => $request->request->getString('menu'),
            'date_soumise' => null !== ($menuSoumis = $this->entiteSelectionnee($request->request->getString('menu'), $menusActifs)) ? $menuSoumis->getDateMenu()->format('Y-m-d') : '',
        ]);
    }

    #[Route('/sortie-consommation', name: 'app_sortie_consommation_sans_sejour', methods: ['GET'])]
    public function sansSejour(): Response
    {
        return $this->render('sortie_consommation/index.html.twig', ['sejour' => null]);
    }

    /** @template T of object @param list<T> $entites @return T|null */
    private function entiteSelectionnee(string $id, array $entites): ?object
    {
        if (!Uuid::isValid($id)) return null;
        foreach ($entites as $entite) {
            if ((string) $entite->getId() === $id) return $entite;
        }
        return null;
    }
}
