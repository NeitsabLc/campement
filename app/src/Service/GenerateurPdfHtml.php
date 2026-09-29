<?php

declare(strict_types=1);

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class GenerateurPdfHtml
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function generer(
        string $html,
        string $police,
        string $orientation = 'portrait',
    ): string {
        $options = new Options();
        $repertoireTemporaire = sys_get_temp_dir();
        $options->setTempDir($repertoireTemporaire);
        $options->setFontDir($repertoireTemporaire);
        $options->setFontCache($repertoireTemporaire);
        $options->setChroot($this->projectDir);
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont($police);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', $orientation);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }
}
