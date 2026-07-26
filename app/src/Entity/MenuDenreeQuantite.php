<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuDenreeQuantiteRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: MenuDenreeQuantiteRepository::class)]
#[ORM\Table(name: 'menu_denree_quantite', schema: 'campement')]
#[ORM\UniqueConstraint(
    name: 'uq_menu_denree_quantite',
    columns: ['menu_denree_id', 'sejour_public_cible_id'],
)]
#[ORM\Index(name: 'idx_menu_denree_quantite_menu_denree', columns: ['menu_denree_id'])]
#[ORM\Index(name: 'idx_menu_denree_quantite_sejour_public_cible', columns: ['sejour_public_cible_id'])]
#[ORM\HasLifecycleCallbacks]
class MenuDenreeQuantite
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'quantites')]
    #[ORM\JoinColumn(name: 'menu_denree_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MenuDenree $menuDenree;

    #[ORM\ManyToOne(inversedBy: 'quantitesMenu')]
    #[ORM\JoinColumn(
        name: 'sejour_public_cible_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private SejourPublicCible $sejourPublicCible;

    #[ORM\Column(name: 'quantite_individuelle', type: Types::DECIMAL, precision: 12, scale: 3)]
    private string $quantiteIndividuelle;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $maintenant = new DateTimeImmutable();
        $this->id = new UuidV7();
        $this->createdAt = $maintenant;
        $this->updatedAt = $maintenant;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getMenuDenree(): MenuDenree
    {
        return $this->menuDenree;
    }

    public function setMenuDenree(MenuDenree $menuDenree): self
    {
        $this->menuDenree = $menuDenree;

        return $this;
    }

    public function getSejourPublicCible(): SejourPublicCible
    {
        return $this->sejourPublicCible;
    }

    public function setSejourPublicCible(SejourPublicCible $sejourPublicCible): self
    {
        $this->sejourPublicCible = $sejourPublicCible;

        return $this;
    }

    public function getQuantiteIndividuelle(): string
    {
        return $this->quantiteIndividuelle;
    }

    public function setQuantiteIndividuelle(string $quantiteIndividuelle): self
    {
        $this->quantiteIndividuelle = $quantiteIndividuelle;

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
}
