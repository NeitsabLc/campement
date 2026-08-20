<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Recette;
use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use Symfony\Component\HttpFoundation\Request;

final class PresentationMenu
{
    private const REPAS_AVEC_CATEGORIES = ['DEJEUNER', 'DINER'];

    public function date(Request $request, \DateTimeImmutable $debut, \DateTimeImmutable $fin): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('date'));

        return false !== $date && $date >= $debut && $date <= $fin ? $date : $debut;
    }

    /** @param list<SejourTypeRepas> $repas */
    public function repas(string $id, array $repas): SejourTypeRepas
    {
        foreach ($repas as $configuration) {
            if ((string) $configuration->getId() === $id) {
                return $configuration;
            }
        }

        return $repas[0];
    }

    /**
     * @param list<SejourTypeRepas> $repas
     *
     * @return array{date: \DateTimeImmutable, repas: SejourTypeRepas}|null
     */
    public function repasSuivant(
        \DateTimeImmutable $date,
        SejourTypeRepas $repasSelectionne,
        array $repas,
        \DateTimeImmutable $dateFin,
    ): ?array {
        foreach ($repas as $index => $configuration) {
            if ($configuration !== $repasSelectionne) {
                continue;
            }
            if (isset($repas[$index + 1])) {
                return ['date' => $date, 'repas' => $repas[$index + 1]];
            }
            if ($date < $dateFin) {
                return ['date' => $date->modify('+1 day'), 'repas' => $repas[0]];
            }

            return null;
        }

        return null;
    }

    public function avecCategories(string $code): bool
    {
        return in_array($code, self::REPAS_AVEC_CATEGORIES, true);
    }

    public function categorieRecettesPourRepas(string $code): ?string
    {
        return in_array($code, ['PETIT_DEJEUNER', 'GOUTER'], true) ? $code : null;
    }

    public function libelleDate(\DateTimeImmutable $date): string
    {
        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return ucfirst(sprintf('%s %d %s', $jours[(int) $date->format('w')], (int) $date->format('j'), $mois[(int) $date->format('n')]));
    }

    /**
     * @param list<object>                $denrees
     * @param array<string, list<object>> $conditionnements
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalogue(array $denrees, array $conditionnements): array
    {
        $catalogue = [];
        foreach ($denrees as $denree) {
            $id = (string) $denree->getId();
            $catalogue[$id] = [
                'id' => $id,
                'nom' => $denree->getNom(),
                'conditionnements' => array_map(static fn ($unite): array => [
                    'id' => (string) $unite->getId(),
                    'nom' => $unite->getNom(),
                    'symbole' => $unite->getSymbole(),
                ], $conditionnements[$id]),
            ];
        }

        return $catalogue;
    }

    /**
     * @param list<Recette> $recettes
     *
     * @return array<string, array<string, mixed>>
     */
    public function recettesJson(array $recettes): array
    {
        $resultat = [];
        foreach ($recettes as $recette) {
            $lignes = [];
            foreach ($recette->getDenrees() as $ligne) {
                $quantites = [];
                foreach ($ligne->getQuantites() as $quantite) {
                    $quantites[(string) $quantite->getSejourPublicCible()->getId()] = $quantite->getQuantiteIndividuelle();
                }
                $lignes[] = [
                    'denree' => (string) $ligne->getDenree()->getId(),
                    'conditionnement' => (string) $ligne->getConditionnement()->getId(),
                    'regime' => $ligne->getRegime()?->value,
                    'quantites' => $quantites,
                ];
            }
            $resultat[(string) $recette->getId()] = [
                'nom' => $recette->getNom(),
                'categorie' => $recette->getCategorie(),
                'lignes' => $lignes,
            ];
        }

        return $resultat;
    }

    /** @return list<\DateTimeImmutable> */
    public function jours(Sejour $sejour): array
    {
        return iterator_to_array(new \DatePeriod($sejour->getDateDebut(), new \DateInterval('P1D'), $sejour->getDateFin()->modify('+1 day')));
    }

    /** @return array<string, array<string, mixed>> */
    public function composition(?Menu $menu, bool $avecCategories): array
    {
        $composition = [];
        if (null === $menu) {
            return $composition;
        }
        foreach ($menu->getDenrees() as $ligne) {
            $categorie = $avecCategories ? ($ligne->getCategorie() ?? 'PLAT') : '';
            $instance = $ligne->getRecetteInstanceId();
            $recette = $ligne->getRecette();
            if (null !== $instance && null !== $recette) {
                $cle = (string) $instance;
                $composition[$categorie]['recettes'][$cle] ??= [
                    'id' => (string) $recette->getId(),
                    'nom' => $recette->getNom(),
                    'instance' => $cle,
                    'lignes' => [],
                ];
                $composition[$categorie]['recettes'][$cle]['lignes'][] = $ligne;
            } else {
                $composition[$categorie]['supplementaires'][] = $ligne;
            }
        }

        return $composition;
    }

    /**
     * @param list<array<string, mixed>> $statuts
     *
     * @return array<string, bool>
     */
    public function menusExistants(array $statuts): array
    {
        $resultat = [];
        foreach ($statuts as $statut) {
            $cle = $statut['specialCode']
                ? 'special|'.$statut['specialCode']
                : $statut['dateMenu']?->format('Y-m-d').'|'.$statut['repasId'];
            $resultat[$cle] = (int) $statut['nombreDenrees'] > 0;
        }

        return $resultat;
    }

    /**
     * @param list<Menu>            $menus
     * @param list<SejourTypeRepas> $repas
     *
     * @return list<array<string, mixed>>
     */
    public function menusDuJour(array $menus, array $repas): array
    {
        $parRepas = [];
        foreach ($menus as $menu) {
            if (null !== ($configuration = $menu->getSejourTypeRepas())) {
                $parRepas[(string) $configuration->getId()] = $menu;
            }
        }
        $resultat = [];
        foreach ($repas as $configuration) {
            $menu = $parRepas[(string) $configuration->getId()] ?? null;
            $codeRepas = $configuration->getTypeRepas()->getCode();
            $avecCategories = $this->avecCategories($codeRepas);
            $categories = [];
            foreach ($avecCategories ? Recette::CATEGORIES_MENU : [''] as $codeCategorie) {
                $recettes = [];
                $regimesRecettes = [];
                $supplementaires = [];
                if (null !== $menu) {
                    foreach ($menu->getDenrees() as $ligne) {
                        $categorie = $avecCategories ? ($ligne->getCategorie() ?? 'PLAT') : '';
                        if ($categorie !== $codeCategorie) {
                            continue;
                        }
                        if (null !== ($recette = $ligne->getRecette())) {
                            $instance = (string) ($ligne->getRecetteInstanceId() ?? $recette->getId());
                            $recettes[$instance] = $recette->getNom();
                            if (null !== ($regime = $ligne->getRegime())) {
                                $regimesRecettes[$instance][$regime->value] = $regime->libelle();
                            }
                        } else {
                            $supplementaires[] = $ligne->getDenree()->getNom().(null === $ligne->getRegime()
                                ? ''
                                : ' — '.$ligne->getRegime()->libelle());
                        }
                    }
                }
                foreach ($recettes as $instance => $nom) {
                    if (isset($regimesRecettes[$instance])) {
                        $recettes[$instance] = $nom.' — '.implode(', ', $regimesRecettes[$instance]);
                    }
                }
                $categories[] = [
                    'code' => $codeCategorie,
                    'libelle' => '' === $codeCategorie ? $configuration->getTypeRepas()->getLibelle() : ucfirst(strtolower($codeCategorie)),
                    'recettes' => array_values($recettes),
                    'supplementaires' => $supplementaires,
                ];
            }
            $resultat[] = ['libelle' => $configuration->getTypeRepas()->getLibelle(), 'code' => $codeRepas, 'categories' => $categories];
        }

        return $resultat;
    }
}
