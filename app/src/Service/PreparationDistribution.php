<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use App\Repository\MenuRepository;
use App\Repository\SejourTypeRepasRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PreparationDistribution
{
    public function __construct(
        private readonly MenuRepository $menus,
        private readonly SejourTypeRepasRepository $typesRepas,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Crée les déjeuners vides nécessaires à la fusion des goûters.
     * Cette méthode doit uniquement être appelée depuis un parcours authentifié.
     */
    public function completerDejeuners(Sejour $sejour, ?Menu $menuEnCours = null): int
    {
        if (!$sejour->isDistribuerGouterDejeuner()) {
            return 0;
        }

        $dejeuner = null;
        foreach ($this->typesRepas->findActifsPourSejour($sejour) as $typeRepas) {
            if ('DEJEUNER' === $typeRepas->getTypeRepas()->getCode()) {
                $dejeuner = $typeRepas;
                break;
            }
        }
        if (!$dejeuner instanceof SejourTypeRepas) {
            return 0;
        }

        $menus = $this->menus->findActifsPourSejour($sejour);
        if ($menuEnCours instanceof Menu && !in_array($menuEnCours, $menus, true)) {
            $menus[] = $menuEnCours;
        }

        $datesAvecDejeuner = [];
        foreach ($menus as $menu) {
            if ($this->repasEst($menu, 'DEJEUNER') && null !== $menu->getDateMenu()) {
                $datesAvecDejeuner[$menu->getDateMenu()->format('Y-m-d')] = true;
            }
        }

        $crees = 0;
        foreach ($menus as $menu) {
            $date = $menu->getDateMenu();
            if (!$this->repasEst($menu, 'GOUTER')
                || $menu->getDenrees()->isEmpty()
                || null === $date
                || isset($datesAvecDejeuner[$date->format('Y-m-d')])) {
                continue;
            }

            $nouveau = (new Menu())
                ->setSejour($sejour)
                ->setDateMenu($date)
                ->setSejourTypeRepas($dejeuner);
            $this->entityManager->persist($nouveau);
            $datesAvecDejeuner[$date->format('Y-m-d')] = true;
            ++$crees;
        }

        return $crees;
    }

    private function repasEst(Menu $menu, string $code): bool
    {
        return !$menu->isSpecial() && $menu->getSejourTypeRepas()?->getTypeRepas()->getCode() === $code;
    }
}
