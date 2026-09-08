<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\Recette;
use App\Entity\SejourTypeRepas;
use App\Entity\Utilisateur;
use App\Enum\RegimeAlimentaire;
use App\Repository\DenreeRepository;
use App\Repository\MenuRepository;
use App\Repository\RecetteRepository;
use App\Repository\SejourPublicCibleRepository;
use App\Repository\SejourTypeRepasRepository;
use App\Repository\UniteRepository;
use App\Service\ContexteSejour;
use App\Service\ConversionConditionnement;
use App\Service\PreparationDistribution;
use App\Service\PresentationMenu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(new Expression("is_granted('ROLE_GESTIONNAIRE') or is_granted('ROLE_GROUPE')"))]
final class MenuController extends AbstractController
{
    private const CATEGORIES = Recette::CATEGORIES_MENU;
    private const SPECIAUX = [
        'EXPLO' => 'Explo',
        'PIQUE_NIQUE_1' => 'Pique-nique 1',
        'PIQUE_NIQUE_2' => 'Pique-nique 2',
    ];

    #[Route('/menus', name: 'app_menus', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContexteSejour $contexte,
        SejourTypeRepasRepository $repasRepository,
        MenuRepository $menus,
        DenreeRepository $denrees,
        SejourPublicCibleRepository $publics,
        RecetteRepository $recettes,
        UniteRepository $unites,
        ConversionConditionnement $conversion,
        PreparationDistribution $preparationDistribution,
        PresentationMenu $presentation,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $contexte->actif();
        $lectureSeule = $this->isGranted(Utilisateur::ROLE_GROUPE);
        if (null === $sejour) {
            return $this->render('menu/index.html.twig', ['sejour' => null]);
        }

        $repas = $repasRepository->findActifsPourSejour($sejour);
        if ([] === $repas) {
            return $this->render('menu/index.html.twig', ['sejour' => $sejour, 'repas' => []]);
        }

        $date = $presentation->date($request, $sejour->getDateDebut(), $sejour->getDateFin());
        $repasSelectionne = $presentation->repas($request->query->getString('repas'), $repas);
        if ($lectureSeule && $request->isMethod('POST')) {
            throw $this->createAccessDeniedException('Les menus sont accessibles en lecture seule.');
        }
        if ($lectureSeule) {
            return $this->render('menu/groupe.html.twig', [
                'sejour' => $sejour,
                'date_selectionnee' => $date,
                'date_libelle' => $presentation->libelleDate($date),
                'jour_precedent' => $date > $sejour->getDateDebut() ? $date->modify('-1 day') : null,
                'jour_suivant' => $date < $sejour->getDateFin() ? $date->modify('+1 day') : null,
                'menus_jour' => $presentation->menusDuJour($menus->findPourDate($sejour, $date), $repas),
            ]);
        }

        $pageSpeciaux = $request->query->getBoolean('speciaux');
        $specialDemande = $request->query->getString('special');
        $special = $pageSpeciaux && array_key_exists($specialDemande, self::SPECIAUX) ? $specialDemande : null;
        $publicsActifs = $publics->findActifsPourSejour($sejour);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_menu', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $soumissions = [];
            if ($pageSpeciaux) {
                if (null === $special) {
                    $this->addFlash('error', 'Sélectionnez un repas spécial valide.');

                    return $this->redirectToRoute('app_menus', ['speciaux' => 1]);
                }
                $soumissions[] = [
                    'repas' => $repasSelectionne,
                    'special' => $special,
                    'menu' => $menus->findSpecial($sejour, $special),
                    'avec_categories' => false,
                    'lignes' => $request->request->all('lignes'),
                ];
            } else {
                $repasSoumis = $request->request->all('repas');
                foreach ($repas as $configuration) {
                    $donneesRepas = $repasSoumis[(string) $configuration->getId()] ?? [];
                    $soumissions[] = [
                        'repas' => $configuration,
                        'special' => null,
                        'menu' => $menus->findPourRepas($sejour, $date, $configuration),
                        'avec_categories' => $presentation->avecCategories($configuration->getTypeRepas()->getCode()),
                        'lignes' => is_array($donneesRepas) && is_array($donneesRepas['lignes'] ?? null) ? $donneesRepas['lignes'] : [],
                    ];
                }
            }

            foreach ($soumissions as $soumission) {
                $composition = [];
                foreach ($soumission['lignes'] as $donnees) {
                    if (!is_array($donnees)) {
                        continue;
                    }
                    $denreeId = (string) ($donnees['denree'] ?? '');
                    $uniteId = (string) ($donnees['conditionnement'] ?? '');
                    $denree = Uuid::isValid($denreeId) ? $denrees->find($denreeId) : null;
                    $unite = Uuid::isValid($uniteId) ? $unites->find($uniteId) : null;
                    if (null === $denree
                        || $denree->getSejour() !== $sejour
                        || null === $unite
                        || !in_array($unite, $conversion->conditionnementsPour($denree), true)) {
                        continue;
                    }

                    $quantites = [];
                    foreach ($publicsActifs as $public) {
                        $valeur = str_replace(',', '.', trim((string) ($donnees['quantites'][(string) $public->getId()] ?? '')));
                        if (!is_numeric($valeur) || (float) $valeur < 0) {
                            $this->addFlash('error', sprintf('Quantité invalide pour %s.', $denree->getNom()));

                            return $this->redirectMenu($date, $repasSelectionne, $special, $pageSpeciaux);
                        }
                        $quantites[(string) $public->getId()] = number_format((float) $valeur, 3, '.', '');
                    }
                    $categorie = $soumission['avec_categories'] && in_array($donnees['categorie'] ?? null, self::CATEGORIES, true)
                        ? $donnees['categorie']
                        : null;
                    $regimeBrut = (string) ($donnees['regime'] ?? '');
                    $regime = '' === $regimeBrut ? null : RegimeAlimentaire::tryFrom($regimeBrut);
                    if ('' !== $regimeBrut && null === $regime) {
                        $this->addFlash('error', sprintf('Régime alimentaire invalide pour %s.', $denree->getNom()));

                        return $this->redirectMenu($date, $repasSelectionne, $special, $pageSpeciaux);
                    }
                    $recetteId = (string) ($donnees['recette'] ?? '');
                    $instanceId = (string) ($donnees['recette_instance'] ?? '');
                    $recette = Uuid::isValid($recetteId) ? $recettes->find($recetteId) : null;
                    $instance = Uuid::isValid($instanceId) ? Uuid::fromString($instanceId) : null;
                    if (null === $recette || $recette->getSejour() !== $sejour || !$recette->isActif()) {
                        $recette = null;
                        $instance = null;
                    }
                    $composition[] = [$denree, $unite, $categorie, $regime, $quantites, $recette, $instance];
                }

                $menu = $soumission['menu'] ?? (new Menu())->setSejour($sejour);
                if (null !== $soumission['special']) {
                    $menu->setSpecialCode($soumission['special']);
                } else {
                    $menu->setDateMenu($date)->setSejourTypeRepas($soumission['repas']);
                }
                foreach ($menu->getDenrees()->toArray() as $ancienne) {
                    $menu->removeDenree($ancienne);
                }
                foreach ($composition as $ordre => [$denree, $unite, $categorie, $regime, $quantites, $recette, $instance]) {
                    $ligne = (new MenuDenree())
                        ->setDenree($denree)
                        ->setConditionnement($unite)
                        ->setCategorie($categorie)
                        ->setRegime($regime)
                        ->setRecette($recette)
                        ->setRecetteInstanceId($instance)
                        ->setOrdre($ordre);
                    foreach ($publicsActifs as $public) {
                        $ligne->addQuantite((new MenuDenreeQuantite())
                            ->setSejourPublicCible($public)
                            ->setQuantiteIndividuelle($quantites[(string) $public->getId()]));
                    }
                    $menu->addDenree($ligne);
                }
                $entityManager->persist($menu);
            }

            $entityManager->flush();
            $preparationDistribution->completerDejeuners($sejour);
            $entityManager->flush();
            $this->addFlash('success', $pageSpeciaux ? 'Le repas spécial a bien été enregistré.' : 'Les menus de la journée ont bien été enregistrés.');

            return $this->redirectMenu($date, $repasSelectionne, $special, $pageSpeciaux);
        }

        $denreesActives = $denrees->findActifsPourSejour($sejour);
        $catalogue = $presentation->catalogue($denreesActives, $conversion->conditionnementsPourDenrees($denreesActives));
        $recettesActives = $recettes->findActivesPourSejour($sejour);
        $menusDate = $menus->findPourDate($sejour, $date);
        $menusDateParRepas = [];
        foreach ($menusDate as $menuDate) {
            if (null !== ($configuration = $menuDate->getSejourTypeRepas())) {
                $menusDateParRepas[(string) $configuration->getId()] = $menuDate;
            }
        }

        $editeursMenus = [];
        foreach ($repas as $configuration) {
            $code = $configuration->getTypeRepas()->getCode();
            $menuDate = $menusDateParRepas[(string) $configuration->getId()] ?? null;
            $avecCategories = $presentation->avecCategories($code);
            $editeursMenus[] = [
                'id' => (string) $configuration->getId(),
                'code' => $code,
                'libelle' => $configuration->getTypeRepas()->getLibelle(),
                'renseigne' => null !== $menuDate && !$menuDate->getDenrees()->isEmpty(),
                'avec_categories' => $avecCategories,
                'categories_recettes' => $presentation->categoriesRecettesPourRepas($code),
                'composition' => $presentation->composition($menuDate, $avecCategories),
            ];
        }
        $editeursSpeciaux = [];
        foreach (self::SPECIAUX as $code => $libelle) {
            $menuSpecial = $menus->findSpecial($sejour, $code);
            $editeursSpeciaux[] = [
                'code' => $code,
                'libelle' => $libelle,
                'renseigne' => null !== $menuSpecial && !$menuSpecial->getDenrees()->isEmpty(),
                'avec_categories' => false,
                'categories_recettes' => null,
                'composition' => $presentation->composition($menuSpecial, false),
            ];
        }

        return $this->render('menu/index.html.twig', [
            'sejour' => $sejour,
            'page_speciaux' => $pageSpeciaux,
            'repas' => $repas,
            'repas_selectionne' => $repasSelectionne,
            'date_selectionnee' => $date,
            'publicsCibles' => $publicsActifs,
            'catalogue' => $catalogue,
            'regimes' => RegimeAlimentaire::choix(),
            'recettes' => $recettesActives,
            'recettes_json' => $presentation->recettesJson($recettesActives),
            'editeurs_menus' => $editeursMenus,
            'editeurs_speciaux' => $editeursSpeciaux,
            'date_libelle' => $presentation->libelleDate($date),
            'jour_precedent' => $date > $sejour->getDateDebut() ? $date->modify('-1 day') : null,
            'jour_suivant' => $date < $sejour->getDateFin() ? $date->modify('+1 day') : null,
        ]);
    }

    private function redirectMenu(
        \DateTimeImmutable $date,
        SejourTypeRepas $repas,
        ?string $special,
        bool $pageSpeciaux,
    ): Response {
        return $this->redirectToRoute('app_menus', $pageSpeciaux
            ? ['speciaux' => 1, 'special' => $special]
            : ['date' => $date->format('Y-m-d'), 'repas' => (string) $repas->getId()]);
    }
}
