<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PublicCibleRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PublicCibleRepository::class)]
#[ORM\Table(name: 'public_cible', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_public_cible_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class PublicCible
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 150)]
    private string $libelle;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $ordre = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, SejourPublicCible> */
    #[ORM\OneToMany(mappedBy: 'publicCible', targetEntity: SejourPublicCible::class)]
    private Collection $sejours;

    public function __construct()
    {
        $maintenant = new DateTimeImmutable();
        $this->createdAt = $maintenant;
        $this->updatedAt = $maintenant;
        $this->sejours = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function actualiserDateModification(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /** @return Collection<int, SejourPublicCible> */
    public function getSejours(): Collection
    {
        return $this->sejours;
    }
}
