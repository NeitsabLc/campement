<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\ModeRepasGroupe;
use App\Repository\GroupeRepasRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupeRepasRepository::class)]
#[ORM\Table(name: 'groupe_repas', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_groupe_repas', columns: ['groupe_id', 'menu_id'])]
#[ORM\Index(name: 'idx_groupe_repas_groupe', columns: ['groupe_id'])]
#[ORM\Index(name: 'idx_groupe_repas_menu', columns: ['menu_id'])]
class GroupeRepas
{
    use EntityIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'groupe_id', nullable: false, onDelete: 'CASCADE')]
    private Groupe $groupe;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'menu_id', nullable: false, onDelete: 'CASCADE')]
    private Menu $menu;

    #[ORM\Column(length: 20, enumType: ModeRepasGroupe::class)]
    private ModeRepasGroupe $mode;

    public function __construct(Groupe $groupe, Menu $menu, ModeRepasGroupe $mode)
    {
        if ($groupe->getSejour() !== $menu->getSejour() || $menu->isSpecial()) {
            throw new \InvalidArgumentException('Le repas daté et l’unité doivent appartenir au même séjour.');
        }

        $this->initializeId();
        $this->initializeTimestamps();
        $this->groupe = $groupe;
        $this->menu = $menu;
        $this->mode = $mode;
    }

    public function getGroupe(): Groupe
    {
        return $this->groupe;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function getMode(): ModeRepasGroupe
    {
        return $this->mode;
    }

    public function setMode(ModeRepasGroupe $mode): self
    {
        $this->mode = $mode;
        $this->touch();

        return $this;
    }
}
