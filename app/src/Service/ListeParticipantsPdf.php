<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Groupe;
use App\Entity\Participant;
use App\Entity\Sejour;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ListeParticipantsPdf
{
    private const THEMES = [
        'farfadets' => ['#94c11c', '#eaf4ce'],
        'louveteaux-jeannettes' => ['#e5821f', '#fbe3c8'],
        'scouts-guides' => ['#0089b7', '#cceaf4'],
        'pionniers-caravelles' => ['#d7282f', '#f6d2d4'],
        'compagnons' => ['#00843d', '#cce6d8'],
        'adulte' => ['#332567', '#d9d5e6'],
    ];

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) {}

    /** @param list<Groupe> $groupes @param list<Participant> $participants */
    public function generer(Sejour $sejour, array $groupes, array $participants): string
    {
        $parGroupe = [];
        foreach ($participants as $participant) {
            $parGroupe[(string) $participant->getGroupe()->getId()][$participant->getType()][] = $participant;
        }

        $options = new Options();
        $options->setTempDir(sys_get_temp_dir());
        $options->setFontDir(sys_get_temp_dir());
        $options->setFontCache(sys_get_temp_dir());
        $options->setChroot($this->projectDir);
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($this->html($sejour, $groupes, $parGroupe), 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @param list<Groupe> $groupes @param array<string, array<string, list<Participant>>> $parGroupe */
    private function html(Sejour $sejour, array $groupes, array $parGroupe): string
    {
        $pages = [];
        foreach ($groupes as $groupe) {
            $listes = $parGroupe[(string) $groupe->getId()] ?? [];
            $jeunes = $listes[Participant::TYPE_JEUNE] ?? [];
            $adultes = $listes[Participant::TYPE_ADULTE] ?? [];
            if ([] === $jeunes && [] === $adultes) continue;
            $contenu = sprintf('<header><h1>%s</h1><p>%s - du %s au %s</p></header>', $this->e(mb_strtoupper($groupe->getNom())), $this->e($sejour->getNom()), $sejour->getDateDebut()->format('d/m/Y'), $sejour->getDateFin()->format('d/m/Y'));
            if ([] !== $jeunes) $contenu .= $this->tableau('Jeunes', 'Coordonnées des parents (tél.)', $jeunes, true);
            if ([] !== $adultes) $contenu .= $this->tableau('Adultes', 'Contact d’urgence (tél.)', $adultes, false);
            $theme = array_key_exists($groupe->getType(), self::THEMES) ? $groupe->getType() : 'adulte';
            $pages[] = sprintf('<section class="page theme-%s">%s</section>', $this->e($theme), $contenu);
        }
        if ([] === $pages) $pages[] = '<section class="page"><header><h1>LISTE DES PARTICIPANTS</h1><p>'.$this->e($sejour->getNom()).'</p></header><p>Aucun participant enregistré.</p></section>';

        return '<!doctype html><html lang="fr"><head><meta charset="UTF-8"><style>'.$this->css().'</style></head><body>'.implode('', $pages).'</body></html>';
    }

    /** @param list<Participant> $participants */
    private function tableau(string $titre, string $contactTitre, array $participants, bool $jeunes): string
    {
        $lignes = '';
        foreach ($participants as $participant) {
            $contact = $jeunes
                ? implode(' / ', array_filter([$participant->getTelephoneParent1(), $participant->getTelephoneParent2()]))
                : (string) $participant->getContactUrgenceTelephone();
            $lignes .= sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->e(mb_strtoupper($participant->getNom())), $this->e($participant->getPrenom()),
                $participant->getDateNaissance()->format('d/m/Y'), $this->e($contact));
        }

        return sprintf('<section class="category"><h2>%s <span>%d</span></h2><table><thead><tr><th>Nom</th><th>Prénom</th><th>DDN</th><th>%s</th></tr></thead><tbody>%s</tbody></table></section>',
            $this->e($titre), count($participants), $this->e($contactTitre), $lignes);
    }

    private function css(): string
    {
        return <<<'CSS'
@page { size:A4 portrait; margin:18mm 15mm 16mm; }
* { box-sizing:border-box; }
body { margin:0; color:#003b60; font-family:'DejaVu Sans', sans-serif; font-size:10.5px; }
.page { page-break-after:always; }
.page:last-child { page-break-after:auto; }
header { margin-bottom:22px; }
h1 { margin:0 0 5px; color:#003b60; font-size:19px; font-weight:800; }
header p { margin:0; color:#567080; font-size:9px; }
.category { margin:0 0 24px; }
h2 { margin:0 0 7px; color:#003b60; font-size:13px; }
h2 span { display:inline-block; min-width:18px; margin-left:4px; padding:2px 5px; border-radius:10px; color:#087b67; background:#dff5ee; font-size:9px; text-align:center; }
table { width:100%; border-collapse:collapse; table-layout:fixed; }
th, td { padding:6px 8px; border:1px solid #003b60; text-align:left; vertical-align:middle; }
th { font-size:10px; font-weight:800; }
td { height:27px; font-size:10px; }
th:nth-child(1), td:nth-child(1) { width:25%; }
th:nth-child(2), td:nth-child(2) { width:22%; }
th:nth-child(3), td:nth-child(3) { width:18%; }
th:nth-child(4), td:nth-child(4) { width:35%; }
.theme-farfadets h1, .theme-farfadets h2 { color:#94c11c; }.theme-farfadets th, .theme-farfadets td { border-color:#94c11c; }.theme-farfadets th { background:#eaf4ce; }
.theme-louveteaux-jeannettes h1, .theme-louveteaux-jeannettes h2 { color:#e5821f; }.theme-louveteaux-jeannettes th, .theme-louveteaux-jeannettes td { border-color:#e5821f; }.theme-louveteaux-jeannettes th { background:#fbe3c8; }
.theme-scouts-guides h1, .theme-scouts-guides h2 { color:#0089b7; }.theme-scouts-guides th, .theme-scouts-guides td { border-color:#0089b7; }.theme-scouts-guides th { background:#cceaf4; }
.theme-pionniers-caravelles h1, .theme-pionniers-caravelles h2 { color:#d7282f; }.theme-pionniers-caravelles th, .theme-pionniers-caravelles td { border-color:#d7282f; }.theme-pionniers-caravelles th { background:#f6d2d4; }
.theme-compagnons h1, .theme-compagnons h2 { color:#00843d; }.theme-compagnons th, .theme-compagnons td { border-color:#00843d; }.theme-compagnons th { background:#cce6d8; }
.theme-adulte h1, .theme-adulte h2 { color:#332567; }.theme-adulte th, .theme-adulte td { border-color:#332567; }.theme-adulte th { background:#d9d5e6; }
CSS;
    }

    private function e(string $valeur): string
    {
        return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
