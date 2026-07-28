<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\Sejour;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;
use RuntimeException;
use ZipArchive;

final class ArchiveListesCourses
{
    public function __construct(
        private GroupeRepository $groupes,
        private MenuRepository $menus,
        private ListeCoursesPdf $pdf,
    ) {
    }

    public function generer(Sejour $sejour): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'campement-listes-courses-');
        if (false === $chemin) {
            throw new RuntimeException('Impossible de créer l’archive temporaire.');
        }

        $zip = new ZipArchive();
        if (true !== $zip->open($chemin, ZipArchive::OVERWRITE)) {
            @unlink($chemin);
            throw new RuntimeException('Impossible d’ouvrir l’archive temporaire.');
        }

        $groupes = $this->groupes->findActifsPourSejour($sejour);
        $menus = $this->menus->findActifsPourSejour($sejour);
        $nombreRepas = 0;
        foreach ($menus as $menu) {
            if ($this->ignorer($menu, $sejour)) {
                continue;
            }
            ++$nombreRepas;
            $dossier = $this->dossier($menu);
            $zip->addEmptyDir($dossier);
            foreach ($groupes as $groupe) {
                $zip->addFromString(
                    $dossier.'/'.$this->nomFichier($menu, $groupe),
                    $this->pdf->generer($menu, $groupe, $this->menusFusionnes($menu, $menus, $sejour)),
                );
            }
        }
        if (0 === $nombreRepas) {
            $zip->addFromString(
                'AUCUNE_LISTE.txt',
                "Aucun repas daté et actif n’est actuellement configuré pour ce séjour.\n",
            );
        }
        $zip->close();

        return $chemin;
    }

    private function ignorer(Menu $menu, Sejour $sejour): bool
    {
        if ($menu->isSpecial() || null === $menu->getDateMenu()) {
            return true;
        }

        return $sejour->isDistribuerGouterDejeuner()
            && 'GOUTER' === $menu->getSejourTypeRepas()?->getTypeRepas()->getCode();
    }

    private function dossier(Menu $menu): string
    {
        $date = $menu->getDateMenu();

        return null === $date ? 'sans-date' : $date->format('Y-m-d').'_'.self::slug($menu->getLibelle());
    }

    /**
     * @param list<Menu> $menus
     * @return list<Menu>
     */
    private function menusFusionnes(Menu $menu, array $menus, Sejour $sejour): array
    {
        if (
            !$sejour->isDistribuerGouterDejeuner()
            || 'DEJEUNER' !== $menu->getSejourTypeRepas()?->getTypeRepas()->getCode()
        ) {
            return [];
        }

        return array_values(array_filter(
            $menus,
            static fn (Menu $candidat): bool => $candidat->getDateMenu()?->format('Y-m-d') === $menu->getDateMenu()?->format('Y-m-d')
                && 'GOUTER' === $candidat->getSejourTypeRepas()?->getTypeRepas()->getCode(),
        ));
    }

    private function nomFichier(Menu $menu, Groupe $groupe): string
    {
        return sprintf(
            '%s_%s_%s.pdf',
            self::slug($groupe->getNom()),
            $menu->getDateMenu()?->format('Y-m-d') ?? 'sans-date',
            self::slug($menu->getLibelle()),
        );
    }

    private static function slug(string $valeur): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);
        $slug = strtolower((string) $ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-') ?: 'element';
    }
}
