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
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
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
    private const CATEGORIES = Recette::CATEGORIES;
    private const REPAS_AVEC_CATEGORIES = ['DEJEUNER', 'DINER'];
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
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $contexte->actif();
        if (null === $sejour) {
            return $this->render('menu/index.html.twig', ['sejour' => null]);
        }

        $repas = $repasRepository->findActifsPourSejour($sejour);
        if ([] === $repas) {
            return $this->render('menu/index.html.twig', ['sejour' => $sejour, 'repas' => []]);
        }

        $specialDemande = $request->query->getString('special');
        $special = array_key_exists($specialDemande, self::SPECIAUX) ? $specialDemande : null;
        $date = $this->date($request, $sejour->getDateDebut(), $sejour->getDateFin());
        $repasSelectionne = $this->repas($request->query->getString('repas'), $repas);
        $menu = null !== $special
            ? $menus->findSpecial($sejour, $special)
            : $menus->findPourRepas($sejour, $date, $repasSelectionne);
        $publicsActifs = $publics->findActifsPourSejour($sejour);
        $avecCategories = null === $special && $this->avecCategories($repasSelectionne->getTypeRepas()->getCode());

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
                $composition[] = [$denree, $unite, $categorie, $quantites];
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
            foreach ($composition as $ordre => [$denree, $unite, $categorie, $quantites]) {
                $ligne = (new MenuDenree())
                    ->setDenree($denree)
                    ->setConditionnement($unite)
                    ->setCategorie($categorie)
                    ->setOrdre($ordre);
                foreach ($publicsActifs as $public) {
                    $ligne->addQuantite((new MenuDenreeQuantite())
                        ->setSejourPublicCible($public)
                        ->setQuantiteIndividuelle($quantites[(string) $public->getId()]));
                }
                $menu->addDenree($ligne);
            }

            $entityManager->persist($menu);
            $entityManager->flush();
            $this->addFlash('success', 'Le repas a bien été enregistré.');

            return $this->redirectMenu($date, $repasSelectionne, $special);
        }

        $denreesActives = $denrees->findActifsPourSejour($sejour);
        $conditionnements = $conversion->conditionnementsPourDenrees($denreesActives);
        $catalogue = [];
        foreach ($denreesActives as $denree) {
            $catalogue[(string) $denree->getId()] = [
                'id' => (string) $denree->getId(),
                'nom' => $denree->getNom(),
                'conditionnements' => array_map(static fn ($unite): array => [
                    'id' => (string) $unite->getId(),
                    'nom' => $unite->getNom(),
                    'symbole' => $unite->getSymbole(),
                ], $conditionnements[(string) $denree->getId()]),
            ];
        }

        $recettesActives = $recettes->findActivesPourSejour($sejour);
        $recettesJson = [];
        foreach ($recettesActives as $recette) {
            $lignes = [];
            foreach ($recette->getDenrees() as $ligne) {
                $quantites = [];
                foreach ($ligne->getQuantites() as $quantite) {
                    $quantites[(string) $quantite->getSejourPublicCible()->getId()] = $quantite->getQuantiteIndividuelle();
                }
                $lignes[] = [
                    'denree' => (string) $ligne->getDenree()->getId(),
                    'conditionnement' => (string) $ligne->getConditionnement()->getId(),
                    'quantites' => $quantites,
                ];
            }
            $recettesJson[(string) $recette->getId()] = [
                'nom' => $recette->getNom(),
                'categorie' => $recette->getCategorie(),
                'lignes' => $lignes,
            ];
        }

        $menusExistants = [];
        foreach ($menus->findStatutsPourSejour($sejour) as $statut) {
            $cle = $statut['specialCode']
                ? 'special|'.$statut['specialCode']
                : $statut['dateMenu']?->format('Y-m-d').'|'.$statut['repasId'];
            $menusExistants[$cle] = (int) $statut['nombreDenrees'] > 0;
        }

        $jours = [];
        foreach (new DatePeriod($sejour->getDateDebut(), new DateInterval('P1D'), $sejour->getDateFin()->modify('+1 day')) as $jour) {
            $jours[] = $jour;
        }

        return $this->render('menu/index.html.twig', [
            'sejour' => $sejour,
            'repas' => $repas,
            'repas_selectionne' => $repasSelectionne,
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
            'avec_categories' => $avecCategories,
        ]);
    }

    private function date(Request $request, DateTimeImmutable $debut, DateTimeImmutable $fin): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('date'));

        return false !== $date && $date >= $debut && $date <= $fin ? $date : $debut;
    }

    /** @param list<SejourTypeRepas> $repas */
    private function repas(string $id, array $repas): SejourTypeRepas
    {
        foreach ($repas as $configuration) {
            if ((string) $configuration->getId() === $id) {
                return $configuration;
            }
        }

        return $repas[0];
    }

    private function avecCategories(string $code): bool
    {
        return in_array($code, self::REPAS_AVEC_CATEGORIES, true);
    }

    private function redirectMenu(DateTimeImmutable $date, SejourTypeRepas $repas, ?string $special): Response
    {
        return $this->redirectToRoute('app_menus', null !== $special
            ? ['special' => $special]
            : ['date' => $date->format('Y-m-d'), 'repas' => (string) $repas->getId()]);
    }
}
