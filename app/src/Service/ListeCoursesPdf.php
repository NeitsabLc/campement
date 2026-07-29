<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\Unite;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ListeCoursesPdf
{
    /** @var array<string, string> */
    private const COULEURS = [
        'farfadets' => '#94c11c',
        'louveteaux-jeannettes' => '#e5821f',
        'scouts-guides' => '#0089b7',
        'pionniers-caravelles' => '#d7282f',
        'compagnons' => '#00843d',
        'adulte' => '#332567',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private readonly AffichageQuantite $affichageQuantite,
    ) {
    }

    /** @param list<Menu> $menusSupplementaires */
    public function generer(Menu $menu, Groupe $groupe, array $menusSupplementaires = []): string
    {
        $menus = [$menu, ...$menusSupplementaires];
        $fiches = [];
        if ('adulte' !== $groupe->getType()) {
            $fiches[] = $this->fiche(
                $menu,
                $menus,
                $groupe,
                strtoupper(str_replace('-', '_', $groupe->getType())),
                $groupe->getEffectifJeune(),
                self::COULEURS[$groupe->getType()] ?? '#003a5d',
            );
        }
        $fiches[] = $this->fiche(
            $menu,
            $menus,
            $groupe,
            'ADULTE',
            $groupe->getEffectifAdulte(),
            self::COULEURS['adulte'],
        );

        $options = new Options();
        $repertoireTemporaire = sys_get_temp_dir();
        $options->setTempDir($repertoireTemporaire);
        $options->setFontDir($repertoireTemporaire);
        $options->setFontCache($repertoireTemporaire);
        $options->setChroot($this->projectDir);
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Caveat Brush');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml($this->html($fiches), 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param list<Menu> $menus
     * @return array{titre: string, groupe: string, effectif: int, couleur: string, lignes: list<array{nom: string, individuelle: string, unite: string}>, legende: string}
     */
    private function fiche(Menu $menu, array $menus, Groupe $groupe, string $codePublic, int $effectif, string $couleur): array
    {
        $cumuls = [];
        /** @var array<string, Unite> $unites */
        $unites = [];
        foreach ($menus as $source) {
            foreach ($source->getDenrees() as $ligne) {
                $cle = (string) $ligne->getDenree()->getId();
                $cumuls[$cle] ??= [
                    'nom' => $ligne->getDenree()->getNom(),
                    'individuelle' => 0.0,
                    'unite' => strtoupper($ligne->getConditionnement()->getSymbole()),
                ];
                $cumuls[$cle]['individuelle'] += $this->quantitePourCode($ligne, $codePublic);
                $unites[(string) $ligne->getConditionnement()->getId()] = $ligne->getConditionnement();
            }
        }

        $lignes = [];
        foreach ($cumuls as $cumul) {
            $lignes[] = [
                'nom' => $cumul['nom'],
                'individuelle' => $this->affichageQuantite->parPersonne($cumul['individuelle']),
                'unite' => $cumul['unite'],
            ];
        }

        $legende = [];
        foreach ($unites as $unite) {
            $legende[] = strtoupper($unite->getSymbole()).' = '.$unite->getNom();
        }

        return [
            'titre' => $this->titre($menu),
            'groupe' => $groupe->getNom(),
            'effectif' => $effectif,
            'couleur' => $couleur,
            'lignes' => $lignes,
            'legende' => implode(' ; ', $legende),
        ];
    }

    private function quantitePourCode(MenuDenree $ligne, string $code): float
    {
        /** @var MenuDenreeQuantite $quantite */
        foreach ($ligne->getQuantites() as $quantite) {
            if ($quantite->getSejourPublicCible()->getPublicCible()->getCode() === $code) {
                return (float) $quantite->getQuantiteIndividuelle();
            }
        }

        return 0.0;
    }

    private function titre(Menu $menu): string
    {
        $date = $menu->getDateMenu();
        if (null === $date) {
            return $menu->getLibelle();
        }
        $jours = ['Sunday' => 'Dimanche', 'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi'];

        return $jours[$date->format('l')].' '.$date->format('d/m').' - '.mb_strtolower($menu->getLibelle());
    }

    /** @param list<array{titre: string, groupe: string, effectif: int, couleur: string, lignes: list<array{nom: string, individuelle: string, unite: string}>, legende: string}> $fiches */
    private function html(array $fiches): string
    {
        $sections = [];
        foreach ($fiches as $fiche) {
            $lignes = '';
            $nombreLignes = max(14, count($fiche['lignes']));
            for ($index = 0; $index < $nombreLignes; ++$index) {
                $ligne = $fiche['lignes'][$index] ?? ['nom' => '', 'individuelle' => '', 'unite' => ''];
                $lignes .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>&nbsp;</td><td>%s</td></tr>',
                    '' === $ligne['nom'] ? '&nbsp;' : $this->e($ligne['nom']),
                    '' === $ligne['individuelle'] ? '&nbsp;' : $this->e($ligne['individuelle']),
                    '' === $ligne['unite'] ? '&nbsp;' : $this->e($ligne['unite']),
                );
            }
            $sections[] = sprintf(
                '<section class="fiche" style="color:%s"><h1>%s</h1><div class="identite"><strong>Groupe : %s</strong><strong>Effectifs : %d</strong></div><table><thead><tr><th>Ingrédients</th><th>Qtte/pers</th><th>Quantité à prendre</th><th>Unité</th></tr></thead><tbody>%s</tbody></table><p class="legende">%s</p></section>',
                $fiche['couleur'],
                $this->e($fiche['titre']),
                $this->e($fiche['groupe']),
                $fiche['effectif'],
                $lignes,
                $this->e($fiche['legende']),
            );
        }

        return '<!doctype html><html lang="fr"><head><meta charset="UTF-8"><style>'.$this->css().'</style></head><body><main class="page">'.implode('', $sections).'</main></body></html>';
    }

    private function css(): string
    {
        $font = static fn (string $chemin): string => 'file://'.str_replace(' ', '%20', $chemin);

        return sprintf(<<<'CSS'
@font-face { font-family:'Caveat Brush'; src:url('%s') format('truetype'); font-weight:400; }
@page { size:A4 landscape; margin:0; }
* { box-sizing:border-box; }
body { margin:0; color:#111; font-family:'Caveat Brush', cursive; }
.page { padding:36px 34px 24px; white-space:nowrap; }
.fiche { display:inline-block; width:48.5%%; margin-right:2%%; white-space:normal; vertical-align:top; }
.fiche:last-child { margin-right:0; }
h1 { margin:0 0 20px; color:inherit; font-family:'Caveat Brush', cursive; font-size:29px; font-weight:400; }
.identite { margin-bottom:6px; color:inherit; font-size:16px; line-height:1.45; }
.identite strong { display:block; }
table { width:100%%; border-collapse:collapse; table-layout:fixed; font-size:12px; }
th, td { height:23px; padding:1px 3px; border:1px solid #111; text-align:center; vertical-align:middle; }
th { color:inherit; font-weight:700; }
th:nth-child(1), td:nth-child(1) { width:31%%; }
th:nth-child(2), td:nth-child(2) { width:21%%; }
th:nth-child(3), td:nth-child(3) { width:30%%; }
th:nth-child(4), td:nth-child(4) { width:18%%; }
td { color:inherit; }
.legende { width:100%%; margin:5px 0 0; color:inherit; font-size:11px; }
CSS,
            $font($this->projectDir.'/assets/fonts/caveat-brush/CaveatBrush-Regular.ttf'),
        );
    }

    private function e(string $valeur): string
    {
        return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
