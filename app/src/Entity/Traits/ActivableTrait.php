<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait ActivableTrait
{
    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        $this->touch();

        return $this;
    }
}
