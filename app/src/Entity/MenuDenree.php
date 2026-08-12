<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuDenreeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: MenuDenreeRepository::class)]
#[ORM\Table(name: 'menu_denree', schema: 'campement')]
#[ORM\Index(name: 'idx_menu_denree_menu', columns: ['menu_id'])]
#[ORM\Index(name: 'idx_menu_denree_denree', columns: ['denree_id'])]
#[ORM\HasLifecycleCallbacks]
class MenuDenree
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'denrees')]
    #[ORM\JoinColumn(name: 'menu_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Menu $menu;

    #[ORM\ManyToOne(inversedBy: 'menusDenrees')]
    #[ORM\JoinColumn(name: 'denree_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Denree $denree;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'conditionnement_id', nullable: false, onDelete: 'RESTRICT')]
    private Unite $conditionnement;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'recette_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Recette $recette = null;

    #[ORM\Column(name: 'recette_instance_id', type: 'uuid', nullable: true)]
    private ?Uuid $recetteInstanceId = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $ordre = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, MenuDenreeQuantite> */
    #[ORM\OneToMany(
        mappedBy: 'menuDenree',
        targetEntity: MenuDenreeQuantite::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $quantites;

    public function __construct()
    {
        $maintenant = new \DateTimeImmutable();
        $this->id = new UuidV7();
        $this->createdAt = $maintenant;
        $this->updatedAt = $maintenant;
        $this->quantites = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function setMenu(Menu $menu): self
    {
        $this->menu = $menu;

        return $this;
    }

    public function getDenree(): Denree
    {
        return $this->denree;
    }

    public function setDenree(Denree $denree): self
    {
        $this->denree = $denree;

        return $this;
    }

    public function getConditionnement(): Unite
    {
        return $this->conditionnement;
    }

    public function setConditionnement(Unite $conditionnement): self
    {
        $this->conditionnement = $conditionnement;

        return $this;
    }

    public function getRecette(): ?Recette
    {
        return $this->recette;
    }

    public function setRecette(?Recette $recette): self
    {
        $this->recette = $recette;

        return $this;
    }

    public function getRecetteInstanceId(): ?Uuid
    {
        return $this->recetteInstanceId;
    }

    public function setRecetteInstanceId(?Uuid $id): self
    {
        $this->recetteInstanceId = $id;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): self
    {
        $this->categorie = $categorie;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function actualiserDateModification(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, MenuDenreeQuantite> */
    public function getQuantites(): Collection
    {
        return $this->quantites;
    }

    public function addQuantite(MenuDenreeQuantite $quantite): self
    {
        if ($quantite->getSejourPublicCible()->getSejour() !== $this->denree->getSejour()) {
            throw new \InvalidArgumentException('Le public cible et la denrée doivent appartenir au même séjour.');
        }

        if (!$this->quantites->contains($quantite)) {
            $this->quantites->add($quantite);
            $quantite->setMenuDenree($this);
        }

        return $this;
    }

    public function removeQuantite(MenuDenreeQuantite $quantite): self
    {
        $this->quantites->removeElement($quantite);

        return $this;
    }
}
