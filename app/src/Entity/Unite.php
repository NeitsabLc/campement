<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\ActivableTrait;
use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\UniteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UniteRepository::class)]
#[ORM\Table(name: 'unite', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_unite_nom', columns: ['nom'])]
#[ORM\UniqueConstraint(name: 'uq_unite_symbole', columns: ['symbole'])]
class Unite
{
    use EntityIdTrait;
    use TimestampableTrait;
    use ActivableTrait;

    #[ORM\Column(length: 50)]
    private string $nom;

    #[ORM\Column(length: 10)]
    private string $symbole;

    #[ORM\Column(name: 'utilisable_conditionnement', options: ['default' => true])]
    private bool $utilisableConditionnement = true;

    public function __construct(string $nom, string $symbole)
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->nom = $nom;
        $this->symbole = $symbole;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        $this->touch();

        return $this;
    }

    public function getSymbole(): string
    {
        return $this->symbole;
    }

    public function setSymbole(string $symbole): self
    {
        $this->symbole = $symbole;
        $this->touch();

        return $this;
    }

    public function __toString(): string
    {
        return $this->symbole;
    }

    public function isUtilisableConditionnement(): bool
    {
        return $this->utilisableConditionnement;
    }

    public function setUtilisableConditionnement(bool $valeur): self
    {
        $this->utilisableConditionnement = $valeur;

        return $this;
    }
}
