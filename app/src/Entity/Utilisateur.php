<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_utilisateur_email', columns: ['email'])]
#[ORM\Index(name: 'idx_utilisateur_groupe', columns: ['groupe_id'])]
#[ORM\Index(name: 'idx_utilisateur_jeton_reinitialisation', columns: ['jeton_reinitialisation'])]
#[ORM\HasLifecycleCallbacks]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_GESTIONNAIRE = 'ROLE_GESTIONNAIRE';
    public const ROLE_GROUPE = 'ROLE_GROUPE';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_TECHNIQUE = 'ROLE_TECHNIQUE';

    private const ROLES_AUTORISES = [
        self::ROLE_GESTIONNAIRE,
        self::ROLE_GROUPE,
        self::ROLE_ADMIN,
        self::ROLE_TECHNIQUE,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'utilisateurs')]
    #[ORM\JoinColumn(name: 'groupe_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Groupe $groupe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'dernier_sejour_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Sejour $dernierSejour = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(name: 'mot_de_passe', length: 255)]
    private string $motDePasse;

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 100)]
    private string $nom;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSONB)]
    private array $roles = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'desactive_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $desactiveAt = null;

    #[ORM\Column(name: 'changement_mot_de_passe_requis', options: ['default' => false])]
    private bool $changementMotDePasseRequis = false;

    #[ORM\Column(name: 'jeton_reinitialisation', length: 64, nullable: true)]
    private ?string $jetonReinitialisation = null;

    #[ORM\Column(name: 'expiration_jeton_reinitialisation', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expirationJetonReinitialisation = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Sejour> */
    #[ORM\ManyToMany(targetEntity: Sejour::class, inversedBy: 'gestionnaires')]
    #[ORM\JoinTable(name: 'utilisateur_sejour', schema: 'campement')]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'sejour_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $sejoursGeres;

    public function __construct()
    {
        $maintenant = new \DateTimeImmutable();
        $this->id = new UuidV7();
        $this->createdAt = $maintenant;
        $this->updatedAt = $maintenant;
        $this->sejoursGeres = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getGroupe(): ?Groupe
    {
        return $this->groupe;
    }

    public function setGroupe(?Groupe $groupe): self
    {
        $this->groupe = $groupe;

        return $this;
    }

    public function getDernierSejour(): ?Sejour
    {
        return $this->dernierSejour;
    }

    public function setDernierSejour(?Sejour $sejour): self
    {
        $this->dernierSejour = $sejour;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->motDePasse;
    }

    public function getMotDePasse(): string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): self
    {
        $this->motDePasse = $motDePasse;

        return $this;
    }

    public function setPassword(string $motDePasse): self
    {
        return $this->setMotDePasse($motDePasse);
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;

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

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getRole(): ?string
    {
        return $this->roles[0] ?? null;
    }

    public function setRole(string $role): self
    {
        if (!in_array($role, self::ROLES_AUTORISES, true)) {
            throw new \InvalidArgumentException(sprintf('Le rôle "%s" n’est pas autorisé.', $role));
        }

        $this->roles = [$role];

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        if ($this->actif !== $actif) {
            $this->desactiveAt = $actif ? null : new \DateTimeImmutable();
        }
        $this->actif = $actif;

        return $this;
    }

    public function getDesactiveAt(): ?\DateTimeImmutable
    {
        return $this->desactiveAt;
    }

    public function isChangementMotDePasseRequis(): bool
    {
        return $this->changementMotDePasseRequis;
    }

    public function setChangementMotDePasseRequis(bool $requis): self
    {
        $this->changementMotDePasseRequis = $requis;

        return $this;
    }

    public function definirJetonReinitialisation(string $jeton, \DateTimeImmutable $expiration): self
    {
        $this->jetonReinitialisation = hash('sha256', $jeton);
        $this->expirationJetonReinitialisation = $expiration;

        return $this;
    }

    public function getJetonReinitialisation(): ?string
    {
        return $this->jetonReinitialisation;
    }

    public function jetonReinitialisationEstValide(string $jeton, ?\DateTimeImmutable $maintenant = null): bool
    {
        return null !== $this->jetonReinitialisation
            && null !== $this->expirationJetonReinitialisation
            && $this->expirationJetonReinitialisation >= ($maintenant ?? new \DateTimeImmutable())
            && hash_equals($this->jetonReinitialisation, hash('sha256', $jeton));
    }

    public function effacerJetonReinitialisation(): self
    {
        $this->jetonReinitialisation = null;
        $this->expirationJetonReinitialisation = null;

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

    /**
     * @return Collection<int, Sejour>
     */
    public function getSejoursGeres(): Collection
    {
        return $this->sejoursGeres;
    }

    public function addSejourGere(Sejour $sejour): self
    {
        if (!$this->sejoursGeres->contains($sejour)) {
            $this->sejoursGeres->add($sejour);
            $sejour->addGestionnaire($this);
        }

        return $this;
    }

    public function removeSejourGere(Sejour $sejour): self
    {
        if ($this->sejoursGeres->removeElement($sejour)) {
            $sejour->removeGestionnaire($this);
        }

        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}
