<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ParticipantRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: ParticipantRepository::class)]
#[ORM\Table(name: 'participant', schema: 'campement')]
#[ORM\Index(name: 'idx_participant_groupe', columns: ['groupe_id'])]
#[ORM\Index(name: 'idx_participant_groupe_type', columns: ['groupe_id', 'type'])]
#[ORM\HasLifecycleCallbacks]
class Participant
{
    public const TYPE_JEUNE = 'jeune';
    public const TYPE_ADULTE = 'adulte';
    public const QUALIFICATIONS = ['BAFD', 'DSF', 'ASF titulaire', 'ASF stagiaire', 'BAFA', 'Autre diplôme'];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(name: 'groupe_id', nullable: false, onDelete: 'CASCADE')]
    private Groupe $groupe;

    #[ORM\Column(length: 10)]
    private string $type;

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\Column(length: 150)]
    private string $prenom;

    #[ORM\Column(name: 'date_naissance', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dateNaissance;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 254, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'telephone_parent_1', length: 30, nullable: true)]
    private ?string $telephoneParent1 = null;

    #[ORM\Column(name: 'telephone_parent_2', length: 30, nullable: true)]
    private ?string $telephoneParent2 = null;

    #[ORM\Column(name: 'email_parents', length: 254, nullable: true)]
    private ?string $emailParents = null;

    #[ORM\Column(name: 'contact_urgence_nom_prenom', length: 300, nullable: true)]
    private ?string $contactUrgenceNomPrenom = null;

    #[ORM\Column(name: 'contact_urgence_telephone', length: 30, nullable: true)]
    private ?string $contactUrgenceTelephone = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSONB)]
    private array $qualifications = [];

    #[ORM\Column(name: 'autre_diplome', length: 255, nullable: true)]
    private ?string $autreDiplome = null;

    #[ORM\Column(name: 'stagiaire_bafa')]
    private bool $stagiaireBafa = false;

    #[ORM\Column(name: 'date_debut_presence', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dateDebutPresence;

    #[ORM\Column(name: 'date_fin_presence', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dateFinPresence;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, DocumentParticipant> */
    #[ORM\OneToMany(mappedBy: 'participant', targetEntity: DocumentParticipant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $documents;

    public function __construct()
    {
        $this->id = new UuidV7();
        $this->createdAt = $this->updatedAt = new DateTimeImmutable();
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }
    public function getGroupe(): Groupe { return $this->groupe; }
    public function setGroupe(Groupe $groupe): self { $this->groupe = $groupe; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): self { $this->prenom = $prenom; return $this; }
    public function getDateNaissance(): DateTimeImmutable { return $this->dateNaissance; }
    public function setDateNaissance(DateTimeImmutable $date): self { $this->dateNaissance = $date; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $valeur): self { $this->telephone = $valeur; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $valeur): self { $this->email = $valeur; return $this; }
    public function getTelephoneParent1(): ?string { return $this->telephoneParent1; }
    public function setTelephoneParent1(?string $valeur): self { $this->telephoneParent1 = $valeur; return $this; }
    public function getTelephoneParent2(): ?string { return $this->telephoneParent2; }
    public function setTelephoneParent2(?string $valeur): self { $this->telephoneParent2 = $valeur; return $this; }
    public function getEmailParents(): ?string { return $this->emailParents; }
    public function setEmailParents(?string $valeur): self { $this->emailParents = $valeur; return $this; }
    public function getContactUrgenceNomPrenom(): ?string { return $this->contactUrgenceNomPrenom; }
    public function setContactUrgenceNomPrenom(?string $valeur): self { $this->contactUrgenceNomPrenom = $valeur; return $this; }
    public function getContactUrgenceTelephone(): ?string { return $this->contactUrgenceTelephone; }
    public function setContactUrgenceTelephone(?string $valeur): self { $this->contactUrgenceTelephone = $valeur; return $this; }
    /** @return list<string> */ public function getQualifications(): array { return $this->qualifications; }
    /** @param list<string> $valeur */ public function setQualifications(array $valeur): self { $this->qualifications = $valeur; return $this; }
    public function getAutreDiplome(): ?string { return $this->autreDiplome; }
    public function setAutreDiplome(?string $valeur): self { $this->autreDiplome = $valeur; return $this; }
    public function isStagiaireBafa(): bool { return $this->stagiaireBafa; }
    public function setStagiaireBafa(bool $valeur): self { $this->stagiaireBafa = $valeur; return $this; }
    public function getDateDebutPresence(): DateTimeImmutable { return $this->dateDebutPresence; }
    public function setDateDebutPresence(DateTimeImmutable $date): self { $this->dateDebutPresence = $date; return $this; }
    public function getDateFinPresence(): DateTimeImmutable { return $this->dateFinPresence; }
    public function setDateFinPresence(DateTimeImmutable $date): self { $this->dateFinPresence = $date; return $this; }
    /** @return Collection<int, DocumentParticipant> */ public function getDocuments(): Collection { return $this->documents; }
    public function hasDocumentType(string $type): bool
    {
        return $this->documents->exists(static fn (int $index, DocumentParticipant $document): bool => $document->getType() === $type);
    }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function actualiserDateModification(): void { $this->updatedAt = new DateTimeImmutable(); }
}
