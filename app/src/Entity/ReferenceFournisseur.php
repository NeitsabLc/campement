<?php

declare(strict_types=1);
namespace App\Entity;

use App\Entity\Traits\ActivableTrait;
use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ReferenceFournisseurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReferenceFournisseurRepository::class)]
#[ORM\Table(name: "denree_fournisseur", schema: "campement")]
#[ORM\UniqueConstraint(name: "uq_denree_fournisseur",columns: ["fournisseur_id", "reference"],),]
#[ORM\Index(name: "idx_denree_fournisseur_fournisseur",columns: ["fournisseur_id"],),]
#[ORM\Index(name: "idx_denree_fournisseur_denree", columns: ["denree_id"])]
class ReferenceFournisseur
{
    use EntityIdTrait;
    use TimestampableTrait;
    use ActivableTrait;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    #[ORM\JoinColumn(name: "fournisseur_id",nullable: false,onDelete: "RESTRICT",),]
    private Fournisseur $fournisseur;

    #[ORM\ManyToOne(targetEntity: Denree::class)]
    #[ORM\JoinColumn(name: "denree_id", nullable: false, onDelete: "RESTRICT")]
    private Denree $denree;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference;

    public function __construct(Fournisseur $fournisseur, Denree $denree, ?string $reference)
    {
        if ($fournisseur->getSejour() !== $denree->getSejour()) {
            throw new \InvalidArgumentException('Le fournisseur et la denrée doivent appartenir au même séjour.');
        }
        $this->initializeId();
        $this->initializeTimestamps();
        $this->fournisseur = $fournisseur;
        $this->denree = $denree;
        $this->reference = $reference;
    }

    public function getFournisseur(): Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(Fournisseur $fournisseur): self
    {
        if ($fournisseur->getSejour() !== $this->denree->getSejour()) {
            throw new \InvalidArgumentException('Le fournisseur et la denrée doivent appartenir au même séjour.');
        }
        $this->fournisseur = $fournisseur;
        $this->touch();

        return $this;
    }

    public function getDenree(): Denree
    {
        return $this->denree;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        $this->touch();
        return $this;
    }

    public function __toString(): string
    {
        return $this->denree->getNom();
    }
}
