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
#[ORM\Table(name: 'groupe', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_groupe_sejour_nom', columns: ['sejour_id', 'nom'])]
#[ORM\Index(name: 'idx_groupe_sejour', columns: ['sejour_id'])]
#[ORM\HasLifecycleCallbacks]
class Groupe
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'sejour_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Sejour $sejour;

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\Column(name: 'effectif_jeune', options: ['default' => 0])]
    private int $effectifJeune = 0;

    #[ORM\Column(name: 'effectif_adulte', options: ['default' => 0])]
    private int $effectifAdulte = 0;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(name: 'date_debut_presence', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dateDebutPresence;

    #[ORM\Column(name: 'date_fin_presence', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dateFinPresence;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, Utilisateur> */
    #[ORM\OneToMany(mappedBy: 'groupe', targetEntity: Utilisateur::class)]
    private Collection $utilisateurs;

    /** @var Collection<int, Participant> */
    #[ORM\OneToMany(mappedBy: 'groupe', targetEntity: Participant::class, cascade: ['remove'])]
    private Collection $participants;

    public function __construct()
    {
        $maintenant = new DateTimeImmutable();
        $this->id = new UuidV7();
        $this->createdAt = $maintenant;
        $this->updatedAt = $maintenant;
        $this->utilisateurs = new ArrayCollection();
        $this->participants = new ArrayCollection();
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

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getEffectifJeune(): int
    {
        return $this->effectifJeune;
    }

    public function setEffectifJeune(int $effectifJeune): self
    {
        $this->effectifJeune = $effectifJeune;

        return $this;
    }

    public function getEffectifAdulte(): int
    {
        return $this->effectifAdulte;
    }

    public function setEffectifAdulte(int $effectifAdulte): self
    {
        $this->effectifAdulte = $effectifAdulte;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDateDebutPresence(): DateTimeImmutable
    {
        return $this->dateDebutPresence;
    }

    public function setDateDebutPresence(DateTimeImmutable $dateDebutPresence): self
    {
        $this->dateDebutPresence = $dateDebutPresence;

        return $this;
    }

    public function getDateFinPresence(): DateTimeImmutable
    {
        return $this->dateFinPresence;
    }

    public function setDateFinPresence(DateTimeImmutable $dateFinPresence): self
    {
        $this->dateFinPresence = $dateFinPresence;

        return $this;
    }

    public function estPresentLe(DateTimeImmutable $date): bool
    {
        return $date >= $this->dateDebutPresence && $date <= $this->dateFinPresence;
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

    /** @return Collection<int, Utilisateur> */
    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function addUtilisateur(Utilisateur $utilisateur): self
    {
        if (!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs->add($utilisateur);
            $utilisateur->setGroupe($this);
        }

        return $this;
    }

    public function removeUtilisateur(Utilisateur $utilisateur): self
    {
        if ($this->utilisateurs->removeElement($utilisateur) && $utilisateur->getGroupe() === $this) {
            $utilisateur->setGroupe(null);
        }

        return $this;
    }

    /** @return Collection<int, Participant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

}
