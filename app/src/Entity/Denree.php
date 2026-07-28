<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
#[ORM\Table(name: 'denree', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_denree_nom', columns: ['sejour_id', 'nom'])]
#[ORM\Index(name: 'idx_denree_sejour', columns: ['sejour_id'])]
#[ORM\Index(name: 'idx_denree_unite', columns: ['unite_reference_id'])]
#[ORM\HasLifecycleCallbacks]
class Denree
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'sejour_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Sejour $sejour;

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unite_reference_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Unite $uniteReference;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unite_inventaire_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Unite $uniteInventaire;

    #[ORM\Column(name: 'stock_min', type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $stockMin = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, MenuDenree> */
    #[ORM\OneToMany(mappedBy: 'denree', targetEntity: MenuDenree::class)]
    private Collection $menusDenrees;

    public function __construct(Sejour $sejour)
    {
        $maintenant = new DateTimeImmutable();
        $this->createdAt = $maintenant;
        $this->updatedAt = $maintenant;
        $this->sejour = $sejour;
        $this->id = new UuidV7();
        $this->menusDenrees = new ArrayCollection();
    }

    public function getSejour(): Sejour
    {
        return $this->sejour;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getUniteReference(): Unite
    {
        return $this->uniteReference;
    }

    public function setUniteReference(Unite $uniteReference): self
    {
        $this->uniteReference = $uniteReference;
        if (!isset($this->uniteInventaire)) { $this->uniteInventaire = $uniteReference; }

        return $this;
    }

    public function getUniteInventaire(): Unite { return $this->uniteInventaire; }
    public function setUniteInventaire(Unite $uniteInventaire): self { $this->uniteInventaire = $uniteInventaire; return $this; }

    public function getStockMin(): ?string
    {
        return $this->stockMin;
    }

    public function setStockMin(?string $stockMin): self
    {
        $this->stockMin = $stockMin;

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


    /** @return Collection<int, MenuDenree> */
    public function getMenusDenrees(): Collection
    {
        return $this->menusDenrees;
    }

}
