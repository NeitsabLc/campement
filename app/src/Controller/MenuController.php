<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\Recette;
use App\Entity\SejourTypeRepas;
use App\Entity\Utilisateur;
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

        $specialDemande = $request->query->getString('special');
        $special = array_key_exists($specialDemande, self::SPECIAUX) ? $specialDemande : null;
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

        $menu = null !== $special
            ? $menus->findSpecial($sejour, $special)
            : $menus->findPourRepas($sejour, $date, $repasSelectionne);
        $publicsActifs = $publics->findActifsPourSejour($sejour);
        $avecCategories = null === $special && $presentation->avecCategories($repasSelectionne->getTypeRepas()->getCode());
        $repasSuivant = null === $special
            ? $presentation->repasSuivant($date, $repasSelectionne, $repas, $sejour->getDateFin())
            : null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_menu', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $composition = [];
            foreach ($request->request->all('lignes') as $donnees) {
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

                        return $this->redirectMenu($date, $repasSelectionne, $special);
                    }
                    $quantites[(string) $public->getId()] = number_format((float) $valeur, 3, '.', '');
                }
                $categorie = $avecCategories && in_array($donnees['categorie'] ?? null, self::CATEGORIES, true)
                    ? $donnees['categorie']
                    : null;
                $recetteId = (string) ($donnees['recette'] ?? '');
                $instanceId = (string) ($donnees['recette_instance'] ?? '');
                $recette = Uuid::isValid($recetteId) ? $recettes->find($recetteId) : null;
                $instance = Uuid::isValid($instanceId) ? Uuid::fromString($instanceId) : null;
                if (null === $recette || $recette->getSejour() !== $sejour || !$recette->isActif()) {
                    $recette = null;
                    $instance = null;
                }
                $composition[] = [$denree, $unite, $categorie, $quantites, $recette, $instance];
            }

            $menu ??= (new Menu())->setSejour($sejour);
            if (null !== $special) {
                $menu->setSpecialCode($special);
            } else {
                $menu->setDateMenu($date)->setSejourTypeRepas($repasSelectionne);
            }
            foreach ($menu->getDenrees()->toArray() as $ancienne) {
                $menu->removeDenree($ancienne);
            }
            foreach ($composition as $ordre => [$denree, $unite, $categorie, $quantites, $recette, $instance]) {
                $ligne = (new MenuDenree())
                    ->setDenree($denree)
                    ->setConditionnement($unite)
                    ->setCategorie($categorie)
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
            $preparationDistribution->completerDejeuners($sejour, $menu);
            $entityManager->flush();
            $this->addFlash('success', 'Le repas a bien été enregistré.');

            if ('suivant' === $request->request->getString('action') && null !== $repasSuivant) {
                return $this->redirectMenu($repasSuivant['date'], $repasSuivant['repas'], null);
            }

            return $this->redirectMenu($date, $repasSelectionne, $special);
        }

        $denreesActives = $denrees->findActifsPourSejour($sejour);
        $conditionnements = $conversion->conditionnementsPourDenrees($denreesActives);
        $catalogue = $presentation->catalogue($denreesActives, $conditionnements);

        $recettesActives = $recettes->findActivesPourSejour($sejour);
        $recettesJson = $presentation->recettesJson($recettesActives);
        $categorieRecettes = null === $special
            ? $presentation->categorieRecettesPourRepas($repasSelectionne->getTypeRepas()->getCode())
            : null;
        $menusExistants = $presentation->menusExistants($menus->findStatutsPourSejour($sejour));
        $jours = $presentation->jours($sejour);
        $compositionMenu = $presentation->composition($menu, $avecCategories);

        return $this->render('menu/index.html.twig', [
            'sejour' => $sejour,
            'repas' => $repas,
            'repas_selectionne' => $repasSelectionne,
            'repas_suivant' => $repasSuivant,
            'date_selectionnee' => $date,
            'menu' => $menu,
            'jours' => $jours,
            'special' => $special,
            'specials' => self::SPECIAUX,
            'menus_existants' => $menusExistants,
            'publicsCibles' => $publicsActifs,
            'catalogue' => $catalogue,
            'recettes' => $recettesActives,
            'recettes_json' => $recettesJson,
            'categorie_recettes' => $categorieRecettes,
            'avec_categories' => $avecCategories,
            'composition_menu' => $compositionMenu,
            'lecture_seule' => $lectureSeule,
            'date_libelle' => $presentation->libelleDate($date),
            'jour_precedent' => $date > $sejour->getDateDebut() ? $date->modify('-1 day') : null,
            'jour_suivant' => $date < $sejour->getDateFin() ? $date->modify('+1 day') : null,
            'menus_jour' => $presentation->menusDuJour($menus->findPourDate($sejour, $date), $repas),
        ]);
    }

    private function redirectMenu(\DateTimeImmutable $date, SejourTypeRepas $repas, ?string $special): Response
    {
        return $this->redirectToRoute('app_menus', null !== $special
            ? ['special' => $special]
            : ['date' => $date->format('Y-m-d'), 'repas' => (string) $repas->getId()]);
    }
}
