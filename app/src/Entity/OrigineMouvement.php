<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\ActivableTrait;
use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\OrigineMouvementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrigineMouvementRepository::class)]
#[ORM\Table(name: 'origine_mouvement', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_origine_mouvement_code', columns: ['code'])]
class OrigineMouvement
{
    use EntityIdTrait;
    use TimestampableTrait;
    use ActivableTrait;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 150)]
    private string $libelle;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $ordre = 0;

    public function __construct(string $code, string $libelle, int $ordre = 0)
    {
        if ($ordre < 0) {
            throw new \InvalidArgumentException('L’ordre doit être positif ou nul.');
        }

        $this->initializeId();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->libelle = $libelle;
        $this->ordre = $ordre;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        $this->touch();

        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        $this->touch();

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        if ($ordre < 0) {
            throw new \InvalidArgumentException('L’ordre doit être positif ou nul.');
        }

        $this->ordre = $ordre;
        $this->touch();

        return $this;
    }

    public function __toString(): string
    {
        return $this->libelle;
    }
}
