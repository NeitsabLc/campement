<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\RegimeAlimentaire;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'recette_denree', schema: 'campement')]
class RecetteDenree
{
    use EntityIdTrait;
    use TimestampableTrait;
    #[ORM\ManyToOne(inversedBy: 'denrees')] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Recette $recette;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Denree $denree;
    #[ORM\ManyToOne] #[ORM\JoinColumn(name: 'conditionnement_id', nullable: false, onDelete: 'RESTRICT')] private Unite $conditionnement;
    #[ORM\Column(length: 20, nullable: true, enumType: RegimeAlimentaire::class)] private ?RegimeAlimentaire $regime = null;
    #[ORM\Column(type: 'smallint')] private int $ordre = 0;
    /** @var Collection<int, RecetteDenreeQuantite> */
    #[ORM\OneToMany(mappedBy: 'recetteDenree', targetEntity: RecetteDenreeQuantite::class, cascade: ['persist'], orphanRemoval: true)] private Collection $quantites;

    public function __construct()
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->quantites = new ArrayCollection();
    }

    public function getRecette(): Recette
    {
        return $this->recette;
    }

    public function setRecette(Recette $v): self
    {
        $this->recette = $v;

        return $this;
    }

    public function getDenree(): Denree
    {
        return $this->denree;
    }

    public function setDenree(Denree $v): self
    {
        $this->denree = $v;

        return $this;
    }

    public function getConditionnement(): Unite
    {
        return $this->conditionnement;
    }

    public function setConditionnement(Unite $v): self
    {
        $this->conditionnement = $v;

        return $this;
    }

    public function getRegime(): ?RegimeAlimentaire
    {
        return $this->regime;
    }

    public function setRegime(?RegimeAlimentaire $regime): self
    {
        $this->regime = $regime;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $v): self
    {
        $this->ordre = $v;

        return $this;
    }

    /** @return Collection<int, RecetteDenreeQuantite> */
    public function getQuantites(): Collection
    {
        return $this->quantites;
    }

    public function addQuantite(RecetteDenreeQuantite $v): self
    {
        if (!$this->quantites->contains($v)) {
            $this->quantites->add($v);
            $v->setRecetteDenree($this);
        }

        return $this;
    }
}
