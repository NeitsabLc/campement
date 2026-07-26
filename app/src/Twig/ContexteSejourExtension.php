<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\ContexteSejour;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class ContexteSejourExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly ContexteSejour $contexte) {}

    public function getGlobals(): array
    {
        return [
            'sejour_actif' => $this->contexte->actif(),
            'sejours_accessibles' => $this->contexte->accessibles(),
        ];
    }
}
