<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Groupe;
use App\Entity\GroupeRepas;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Enum\ModeRepasGroupe;

final class CalculCommande
{
    /**
     * @param list<Menu>        $menus
     * @param list<Groupe>      $groupes
     * @param list<GroupeRepas> $configurations
     *
     * @return list<array{menu: Menu, lignes: list<array{denree: \App\Entity\Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>}>
     */
    public function calculer(array $menus, array $groupes, array $configurations = []): array
    {
        $menusSpeciaux = [];
        $menusDates = [];
        foreach ($menus as $menu) {
            if ($menu->isSpecial()) {
                $menusSpeciaux[$menu->getSpecialCode() ?? ''] = $menu;
            } elseif (null !== $menu->getDateMenu()) {
                $menusDates[] = $menu;
            }
        }
        usort($menusDates, static fn (Menu $a, Menu $b): int => $a->getDateMenu() <=> $b->getDateMenu()
            ?: ($a->getSejourTypeRepas()?->getOrdre() ?? 0) <=> ($b->getSejourTypeRepas()?->getOrdre() ?? 0));

        $modes = [];
        foreach ($configurations as $configuration) {
            $modes[(string) $configuration->getGroupe()->getId()][(string) $configuration->getMenu()->getId()] = $configuration->getMode();
        }

        $commandes = [];
        foreach ($menusDates as $menu) {
            $dateMenu = $menu->getDateMenu();
            if (null === $dateMenu) {
                continue;
            }

            $lignes = [];
            foreach ($groupes as $groupe) {
                if (!$groupe->estPresentLe($dateMenu)) {
                    continue;
                }

                $mode = $modes[(string) $groupe->getId()][(string) $menu->getId()] ?? null;
                if (ModeRepasGroupe::NON_PRIS === $mode) {
                    continue;
                }
                $source = null === $mode ? $menu : ($menusSpeciaux[$mode->value] ?? null);
                if (!$source instanceof Menu) {
                    continue;
                }

                foreach ($source->getDenrees() as $ligneMenu) {
                    $regime = $ligneMenu->getRegime();
                    $cle = implode('|', [
                        (string) $ligneMenu->getDenree()->getId(),
                        null === $regime ? 'STANDARD' : $regime->value,
                        (string) $ligneMenu->getConditionnement()->getId(),
                    ]);
                    $lignes[$cle] ??= [
                        'denree' => $ligneMenu->getDenree(),
                        'regime' => $regime,
                        'quantite' => 0.0,
                        'unite' => $ligneMenu->getConditionnement(),
                    ];
                    $lignes[$cle]['quantite'] += $this->quantitePourGroupe(
                        $ligneMenu,
                        $groupe,
                        $this->quantitesParPublic($ligneMenu),
                    );
                }
            }

            $commandes[] = ['menu' => $menu, 'lignes' => $this->trierLignes($lignes)];
        }

        return $commandes;
    }

    /** @return array<string, float> */
    private function quantitesParPublic(MenuDenree $ligne): array
    {
        $quantites = [];
        foreach ($ligne->getQuantites() as $quantite) {
            $quantites[$quantite->getSejourPublicCible()->getPublicCible()->getCode()] = (float) $quantite->getQuantiteIndividuelle();
        }

        return $quantites;
    }

    /** @param array<string, float> $quantites */
    private function quantitePourGroupe(MenuDenree $ligne, Groupe $groupe, array $quantites): float
    {
        $codePublic = strtoupper(str_replace('-', '_', $groupe->getType()));
        if (null === $ligne->getRegime()) {
            return ($quantites[$codePublic] ?? 0.0) * $groupe->getEffectifJeune()
                + ($quantites['ADULTE'] ?? 0.0) * $groupe->getEffectifAdulte();
        }

        return ($quantites[$codePublic] ?? 0.0) * $groupe->nombrePourRegime($ligne->getRegime());
    }

    /**
     * @param array<string, array{denree: \App\Entity\Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}> $lignes
     *
     * @return list<array{denree: \App\Entity\Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>
     */
    private function trierLignes(array $lignes): array
    {
        $lignes = array_values($lignes);
        usort($lignes, static fn (array $a, array $b): int => strnatcasecmp($a['denree']->getNom(), $b['denree']->getNom())
            ?: strnatcasecmp($a['regime']?->libelle() ?? '', $b['regime']?->libelle() ?? '')
            ?: strnatcasecmp($a['unite']->getNom(), $b['unite']->getNom()));

        return $lignes;
    }
}
