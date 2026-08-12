<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\AffichageQuantite;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AffichageQuantiteExtension extends AbstractExtension
{
    public function __construct(private readonly AffichageQuantite $affichageQuantite)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('quantite_par_personne', $this->affichageQuantite->parPersonne(...)),
        ];
    }
}
