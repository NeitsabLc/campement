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
#[ORM\Table(name: 'menu', schema: 'campement')]
#[ORM\Index(name: 'idx_menu_sejour', columns: ['sejour_id'])]
#[ORM\Index(name: 'idx_menu_date', columns: ['date_menu'])]
#[ORM\Index(name: 'idx_menu_sejour_type_repas', columns: ['sejour_type_repas_id'])]
#[ORM\HasLifecycleCallbacks]
class Menu
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'sejour_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Sejour $sejour;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'sejour_type_repas_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?SejourTypeRepas $sejourTypeRepas = null;

    #[ORM\Column(name: 'date_menu', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dateMenu = null;

    #[ORM\Column(name: 'special_code', length: 20, nullable: true)]
    private ?string $specialCode = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, MenuDenree> */
    #[ORM\OneToMany(
        mappedBy: 'menu',
        targetEntity: MenuDenree::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $denrees;

    public function __construct()
    {
        $maintenant = new DateTimeImmutable();
        $this->id = new UuidV7();
        $this->createdAt = $maintenant;
        $this->updatedAt = $maintenant;
        $this->denrees = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSejour(): Sejour
    {
        return $this->sejour;
    }

    public function setSejour(Sejour $sejour): self
    {
        $this->sejour = $sejour;

        return $this;
    }

    public function getSejourTypeRepas(): ?SejourTypeRepas
    {
        return $this->sejourTypeRepas;
    }

    public function setSejourTypeRepas(SejourTypeRepas $sejourTypeRepas): self
    {
        $this->sejourTypeRepas = $sejourTypeRepas;

        return $this;
    }

    public function getDateMenu(): ?DateTimeImmutable
    {
        return $this->dateMenu;
    }

    public function setDateMenu(DateTimeImmutable $dateMenu): self
    {
        $this->dateMenu = $dateMenu;

        return $this;
    }

    public function getSpecialCode(): ?string { return $this->specialCode; }
    public function setSpecialCode(?string $code): self { $this->specialCode = $code; return $this; }
    public function isSpecial(): bool { return null !== $this->specialCode; }
    public function getLibelle(): string
    {
        return match ($this->specialCode) {
            'EXPLO' => 'Explo', 'PIQUE_NIQUE_1' => 'Pique-nique 1', 'PIQUE_NIQUE_2' => 'Pique-nique 2',
            default => $this->sejourTypeRepas?->getTypeRepas()->getLibelle() ?? '',
        };
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

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
    public function getDenrees(): Collection
    {
        return $this->denrees;
    }

    public function addDenree(MenuDenree $menuDenree): self
    {
        if ($menuDenree->getDenree()->getSejour() !== $this->sejour) {
            throw new \InvalidArgumentException('La denrée doit appartenir au séjour du menu.');
        }

        if (!$this->denrees->contains($menuDenree)) {
            $this->denrees->add($menuDenree);
            $menuDenree->setMenu($this);
        }

        return $this;
    }

    public function removeDenree(MenuDenree $menuDenree): self
    {
        $this->denrees->removeElement($menuDenree);

        return $this;
    }

}
