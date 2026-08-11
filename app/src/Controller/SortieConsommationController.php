<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;
use App\Repository\OrigineMouvementRepository;
use App\Repository\SejourRepository;
use App\Repository\TypeMouvementRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ConversionConditionnement;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class SortieConsommationController extends AbstractController
{
    private const ORDRE_PUBLICS_DISTRIBUTION = [
        'FARFADETS' => 10,
        'LOUVETEAUX_JEANNETTES' => 20,
        'SCOUTS_GUIDES' => 30,
        'PIONNIERS_CARAVELLES' => 40,
        'ADULTE' => 100,
    ];

    #[Route('/distribution/{jeton}', name: 'app_sortie_consommation', requirements: ['jeton' => '[0-9a-fA-F-]{36}'], methods: ['GET', 'POST'])]
    #[RateLimit('public_distribution', methods: ['POST'])]
    public function index(
        string $jeton,
        Request $request,
        SejourRepository $sejours,
        GroupeRepository $groupes,
        MenuRepository $menus,
        TypeMouvementRepository $types,
        OrigineMouvementRepository $origines,
        UtilisateurRepository $utilisateurs,
        ConversionConditionnement $conversion,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = Uuid::isValid($jeton) ? $sejours->findPourDistributionPublique($jeton) : null;
        if (null === $sejour) {
            return $this->render('sortie_consommation/index.html.twig', ['sejour' => null]);
        }

        $groupesActifs = $groupes->findActifsPresentsPourSejour($sejour, new DateTimeImmutable('today'));
        $tousLesMenus = $menus->findActifsPourSejour($sejour);
        $cleSoumission = $request->request->getString('cle_soumission');
        if (!Uuid::isValid($cleSoumission)) {
            $cleSoumission = Uuid::v7()->toRfc4122();
        }
        $menusActifs = array_values(array_filter(
            $tousLesMenus,
            static fn (Menu $menu): bool => !$menu->isSpecial() || !$menu->getDenrees()->isEmpty(),
        ));
        if ($sejour->isDistribuerGouterDejeuner()) {
            $menusActifs = array_values(array_filter(
                $menusActifs,
                fn (Menu $menu): bool => $menu->isSpecial() || !$this->repasEst($menu, 'GOUTER'),
            ));
        }

        $vues = [];
        foreach ($menusActifs as $menu) {
            $vues[(string) $menu->getId()] = $this->vueMenu(
                $menu,
                $tousLesMenus,
                $sejour->isDistribuerGouterDejeuner(),
                $conversion,
            );
        }

        $selection = null;
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sortie_consommation', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $groupe = $this->selection($request->request->getString('groupe'), $groupesActifs);
            $menu = $this->selection($request->request->getString('menu'), $menusActifs);
            if (null === $groupe) {
                $erreurs[] = 'Sélectionnez un groupe valide.';
            }
            if (!$menu instanceof Menu) {
                $erreurs[] = 'Sélectionnez un repas valide.';
            }

            $quantites = [];
            if ($menu instanceof Menu) {
                $quantitesSoumises = $request->request->all('quantites');
                foreach ($vues[(string) $menu->getId()]['lignes'] as $ligne) {
                    $denreeId = (string) $ligne['denree']->getId();
                    $brut = str_replace([' ', ','], ['', '.'], trim((string) ($quantitesSoumises[$denreeId] ?? '')));
                    if ('' === $brut || !is_numeric($brut) || (float) $brut < 0) {
                        $erreurs[] = sprintf('Renseignez une quantité positive ou nulle pour %s.', $ligne['denree']->getNom());
                        continue;
                    }
                    $quantites[$denreeId] = number_format((float) $brut, 3, '.', '');
                }
                if ([] === array_filter($quantites, static fn (string $quantite): bool => (float) $quantite > 0)) {
                    $erreurs[] = 'Au moins une denrée doit avoir une quantité supérieure à zéro.';
                }
            }

            if ([] === $erreurs && null !== $groupe && $menu instanceof Menu) {
                $selection = [
                    'groupe' => $groupe,
                    'menu' => $menu,
                    'vue' => $vues[(string) $menu->getId()],
                    'quantites' => $quantites,
                ];
                if ('confirmer' === $request->request->getString('action')) {
                    $dejaEnregistre = $entityManager->getRepository(MouvementStock::class)->findOneBy(['cleSoumission' => $cleSoumission]);
                    if ($dejaEnregistre instanceof MouvementStock) {
                        return $this->render('sortie_consommation/succes.html.twig', compact('groupe', 'menu'));
                    }
                    $type = $types->findOneBy(['code' => 'SORTIE', 'actif' => true]);
                    $origine = $origines->findOneBy(['code' => 'DISTRIBUTION', 'actif' => true]);
                    $utilisateur = $utilisateurs->loadUserByIdentifier('saisie-consommation@campement.local');
                    if (null === $type || null === $origine || null === $utilisateur) {
                        throw new \RuntimeException('Référentiel incomplet.');
                    }

                    $mouvement = (new MouvementStock($sejour, $utilisateur, $type, $origine))
                        ->setGroupe($groupe)
                        ->setMenu($menu)
                        ->setCleSoumission(Uuid::fromString($cleSoumission))
                        ->setDateMouvement($this->dateMouvementNavigateur($request, $menu));
                    $entityManager->persist($mouvement);
                    foreach ($selection['vue']['lignes'] as $ligne) {
                        $denreeId = (string) $ligne['denree']->getId();
                        $quantite = (float) $quantites[$denreeId];
                        if ($quantite <= 0) {
                            continue;
                        }
                        $mouvementLigne = new MouvementStockLigne(
                            $mouvement,
                            $ligne['denree'],
                            number_format($conversion->versUniteReference($ligne['denree'], $ligne['unite'], $quantite), 3, '.', ''),
                        );
                        $mouvementLigne->setQuantiteUniteInventaire($conversion->formaterQuantiteInventaire(
                            $conversion->quantiteInventaireExacte($ligne['denree'], (float) $mouvementLigne->getQuantiteUniteReference()),
                        ));
                        $mouvementLigne->setConditionnementSortie($ligne['unite']);
                        $entityManager->persist($mouvementLigne);
                    }
                    try {
                        $entityManager->flush();
                    } catch (UniqueConstraintViolationException) {
                        // Deux confirmations concurrentes portant la même clé : la première a gagné.
                    }

                    return $this->render('sortie_consommation/succes.html.twig', compact('groupe', 'menu'));
                }
            }
        }

        $menuSoumis = $this->selection($request->request->getString('menu'), $menusActifs);

        return $this->render('sortie_consommation/index.html.twig', [
            'sejour' => $sejour,
            'groupes' => $groupesActifs,
            'menus' => $menusActifs,
            'vues' => $vues,
            'selection' => $selection,
            'erreurs' => $erreurs,
            'valeurs' => $request->request->all('quantites'),
            'groupe_soumis' => $request->request->getString('groupe'),
            'menu_soumis' => $request->request->getString('menu'),
            'date_soumise' => $menuSoumis instanceof Menu ? ($menuSoumis->getDateMenu()?->format('Y-m-d') ?? 'special') : '',
            'cle_soumission' => $cleSoumission,
        ]);
    }

    #[Route('/sortie-consommation', name: 'app_sortie_consommation_sans_sejour', methods: ['GET'])]
    public function sansSejour(): Response
    {
        return $this->render('sortie_consommation/index.html.twig', ['sejour' => null]);
    }

    private function repasEst(Menu $menu, string $code): bool
    {
        return !$menu->isSpecial() && $menu->getSejourTypeRepas()?->getTypeRepas()->getCode() === $code;
    }

    /**
     * @param list<Menu> $menus
     *
     * @return array{menu: Menu, lignes: list<array<string, mixed>>}
     */
    private function vueMenu(Menu $menu, array $menus, bool $fusion, ConversionConditionnement $conversion): array
    {
        $sources = [$menu];
        if ($fusion && $this->repasEst($menu, 'DEJEUNER')) {
            foreach ($menus as $candidat) {
                if ($candidat->getDateMenu()?->format('Y-m-d') === $menu->getDateMenu()?->format('Y-m-d')
                    && $this->repasEst($candidat, 'GOUTER')) {
                    $sources[] = $candidat;
                }
            }
        }

        $groupes = [];
        foreach ($sources as $source) {
            foreach ($source->getDenrees() as $ligne) {
                $denreeId = (string) $ligne->getDenree()->getId();
                $groupes[$denreeId]['denree'] = $ligne->getDenree();
                $groupes[$denreeId]['sources'][] = $ligne;
            }
        }

        $resultat = [];
        foreach ($groupes as $groupe) {
            $premiereUnite = $groupe['sources'][0]->getConditionnement();
            $memeUnite = true;
            foreach ($groupe['sources'] as $ligne) {
                if ($ligne->getConditionnement() !== $premiereUnite) {
                    $memeUnite = false;
                }
            }
            $unite = $memeUnite ? $premiereUnite : $groupe['denree']->getUniteReference();
            $facteurSortie = $conversion->facteurMinimal($groupe['denree'], $unite) ?? 1;
            $quantites = [];
            foreach ($groupe['sources'] as $ligne) {
                $facteur = $conversion->facteurMinimal($groupe['denree'], $ligne->getConditionnement()) ?? 1;
                foreach ($ligne->getQuantites() as $quantite) {
                    $publicId = (string) $quantite->getSejourPublicCible()->getId();
                    $quantites[$publicId] ??= [
                        'code' => $quantite->getSejourPublicCible()->getPublicCible()->getCode(),
                        'libelle' => $quantite->getSejourPublicCible()->getPublicCible()->getLibelle(),
                        'quantite' => 0.0,
                    ];
                    $quantites[$publicId]['quantite'] += (float) $quantite->getQuantiteIndividuelle() * $facteur / $facteurSortie;
                }
            }
            uasort($quantites, static function (array $a, array $b): int {
                $ordreA = self::ORDRE_PUBLICS_DISTRIBUTION[$a['code']] ?? 90;
                $ordreB = self::ORDRE_PUBLICS_DISTRIBUTION[$b['code']] ?? 90;

                return $ordreA <=> $ordreB ?: $a['libelle'] <=> $b['libelle'];
            });
            $resultat[] = ['denree' => $groupe['denree'], 'unite' => $unite, 'quantites' => $quantites];
        }

        return ['menu' => $menu, 'lignes' => $resultat];
    }

    /**
     * @template T of object
     * @param list<T> $items
     * @return T|null
     */
    private function selection(string $id, array $items): ?object
    {
        if (!Uuid::isValid($id)) {
            return null;
        }
        foreach ($items as $item) {
            if ((string) $item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    private function dateMouvementNavigateur(Request $request, Menu $menu): DateTimeImmutable
    {
        $iso = $request->request->getString('date_navigateur');
        $heure = $request->request->getString('heure_navigateur');
        $decalageBrut = $request->request->getString('decalage_utc');
        $decalage = preg_match('/^-?\d+$/', $decalageBrut) ? (int) $decalageBrut : 0;
        try {
            $instant = '' !== $iso ? new DateTimeImmutable($iso) : new DateTimeImmutable();
        } catch (\Exception) {
            $instant = new DateTimeImmutable();
        }
        if (null === $menu->getDateMenu() || !preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $heure)) {
            return $instant;
        }

        $decalage = max(-840, min(840, $decalage));
        $minutes = -$decalage;
        $signe = $minutes >= 0 ? '+' : '-';
        $minutes = abs($minutes);
        $offset = sprintf('%s%02d:%02d', $signe, intdiv($minutes, 60), $minutes % 60);

        return new DateTimeImmutable($menu->getDateMenu()->format('Y-m-d').'T'.$heure.$offset);
    }
}
