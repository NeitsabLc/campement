<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\ActivableTrait;
use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\SejourPublicCibleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SejourPublicCibleRepository::class)]
#[ORM\Table(name: 'sejour_public_cible', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_sejour_public_cible', columns: ['sejour_id', 'public_cible_id'])]
#[ORM\Index(name: 'idx_sejour_public_cible_sejour', columns: ['sejour_id'])]
#[ORM\Index(name: 'idx_sejour_public_cible_public_cible', columns: ['public_cible_id'])]
class SejourPublicCible
{
    use EntityIdTrait;
    use TimestampableTrait;
    use ActivableTrait;

    #[ORM\ManyToOne(inversedBy: 'publicsCibles')]
    #[ORM\JoinColumn(name: 'sejour_id', nullable: false, onDelete: 'CASCADE')]
    private Sejour $sejour;

    #[ORM\ManyToOne(inversedBy: 'sejours')]
    #[ORM\JoinColumn(name: 'public_cible_id', nullable: false, onDelete: 'RESTRICT')]
    private PublicCible $publicCible;

    /** @var Collection<int, MenuDenreeQuantite> */
    #[ORM\OneToMany(mappedBy: 'sejourPublicCible', targetEntity: MenuDenreeQuantite::class)]
    private Collection $quantitesMenu;

    public function __construct(Sejour $sejour, PublicCible $publicCible)
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->sejour = $sejour;
        $this->publicCible = $publicCible;
        $this->quantitesMenu = new ArrayCollection();
    }

    public function getSejour(): Sejour
    {
        return $this->sejour;
    }

    public function getPublicCible(): PublicCible
    {
        return $this->publicCible;
    }

    /** @return Collection<int, MenuDenreeQuantite> */
    public function getQuantitesMenu(): Collection
    {
        return $this->quantitesMenu;
    }
}
