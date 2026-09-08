<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\ActivableTrait;
use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\SejourTypeRepasRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SejourTypeRepasRepository::class)]
#[ORM\Table(name: 'sejour_type_repas', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_sejour_type_repas', columns: ['sejour_id', 'type_repas_id'], ),]
#[ORM\Index(name: 'idx_sejour_type_repas_sejour', columns: ['sejour_id'])]
#[ORM\Index(name: 'idx_sejour_type_repas_type_repas', columns: ['type_repas_id'], ),]
class SejourTypeRepas
{
    use EntityIdTrait;
    use TimestampableTrait;
    use ActivableTrait;

    #[ORM\ManyToOne(targetEntity: Sejour::class)]
    #[ORM\JoinColumn(name: 'sejour_id', nullable: false, onDelete: 'CASCADE')]
    private Sejour $sejour;

    #[ORM\ManyToOne(targetEntity: TypeRepas::class)]
    #[ORM\JoinColumn(name: 'type_repas_id', nullable: false, onDelete: 'RESTRICT', ),]
    private TypeRepas $typeRepas;

    #[ORM\Column(name: 'distribution_active', options: ['default' => true])]
    private bool $distributionActive = true;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $ordre = 0;

    public function __construct(Sejour $sejour, TypeRepas $typeRepas, int $ordre = 0)
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->sejour = $sejour;
        $this->typeRepas = $typeRepas;
        $this->ordre = $ordre;
    }

    public function getSejour(): Sejour
    {
        return $this->sejour;
    }

    public function getTypeRepas(): TypeRepas
    {
        return $this->typeRepas;
    }

    public function isDistributionActive(): bool
    {
        return $this->distributionActive;
    }

    public function setDistributionActive(bool $distributionActive): self
    {
        $this->distributionActive = $distributionActive;
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
            throw new \InvalidArgumentException('Ordre invalide.');
        }

        $this->ordre = $ordre;
        $this->touch();

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->sejour, $this->typeRepas);
    }
}
