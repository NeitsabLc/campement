<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\SituationParticuliereRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SituationParticuliereRepository::class)]
#[ORM\Table(name: 'situation_particuliere', schema: 'campement')]
#[ORM\Index(name: 'idx_situation_particuliere_sejour_date', columns: ['sejour_id', 'date_situation'])]
class SituationParticuliere
{
    use EntityIdTrait;
    use TimestampableTrait;

    public const INFORMATIONS = [
        'SINISTRE_MATERIEL' => 'Sinistre matériel',
        'SINISTRE_CORPOREL_MINEUR' => 'Sinistre corporel mineur',
        'DECES' => 'Décès',
        'HOSPITALISATION_PLUSIEURS_JOURS' => 'Hospitalisation de plusieurs jours',
        'BLESSURE_GRAVE_RISQUE_INCAPACITE' => 'Blessure grave avec risque d’incapacité',
        'PLUSIEURS_VICTIMES' => 'Plusieurs victimes',
        'INTERVENTION_FORCES_ORDRE' => 'Intervention des forces de l’ordre',
        'DEPOT_PLAINTE' => 'Dépôt de plainte',
        'MISE_EN_PERIL_MINEURS' => 'Mise en péril de la sécurité physique ou morale des mineurs',
        'RISQUE_MEDIATIQUE' => 'Risque médiatique',
        'MALTRAITANCE' => 'Maltraitance',
    ];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'sejour_id', nullable: false, onDelete: 'CASCADE')]
    private Sejour $sejour;

    #[ORM\Column(length: 200)]
    private string $libelle;

    #[ORM\Column(name: 'date_situation', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dateSituation;

    /** @var list<string> */
    #[ORM\Column(name: 'informations_complementaires', type: Types::JSONB)]
    private array $informationsComplementaires = [];

    /** @var Collection<int, Participant> */
    #[ORM\ManyToMany(targetEntity: Participant::class)]
    #[ORM\JoinTable(name: 'situation_particuliere_participant', schema: 'campement')]
    #[ORM\JoinColumn(name: 'situation_particuliere_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'participant_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $participants;

    /** @var Collection<int, TacheSituationParticuliere> */
    #[ORM\OneToMany(mappedBy: 'situation', targetEntity: TacheSituationParticuliere::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $taches;

    public function __construct(Sejour $sejour, string $libelle, \DateTimeImmutable $dateSituation)
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->sejour = $sejour;
        $this->participants = new ArrayCollection();
        $this->taches = new ArrayCollection();
        $this->setLibelle($libelle);
        $this->dateSituation = $dateSituation;
    }

    public function getSejour(): Sejour
    {
        return $this->sejour;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $libelle = trim($libelle);
        if ('' === $libelle || mb_strlen($libelle) > 200) {
            throw new \InvalidArgumentException('Le libellé est obligatoire et limité à 200 caractères.');
        }
        $this->libelle = $libelle;
        $this->touch();

        return $this;
    }

    public function getDateSituation(): \DateTimeImmutable
    {
        return $this->dateSituation;
    }

    public function setDateSituation(\DateTimeImmutable $date): self
    {
        $this->dateSituation = $date;
        $this->touch();

        return $this;
    }

    /** @return list<string> */
    public function getInformationsComplementaires(): array
    {
        return $this->informationsComplementaires;
    }

    /** @param list<string> $informations */
    public function setInformationsComplementaires(array $informations): self
    {
        $invalides = array_diff($informations, array_keys(self::INFORMATIONS));
        if ([] !== $invalides) {
            throw new \InvalidArgumentException('Une information complémentaire est invalide.');
        }
        $this->informationsComplementaires = array_values(array_unique($informations));
        $this->touch();

        return $this;
    }

    /** @return Collection<int, Participant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(Participant $participant): self
    {
        if ($participant->getGroupe()->getSejour() !== $this->sejour) {
            throw new \InvalidArgumentException('Le participant doit appartenir au séjour de la situation.');
        }
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
        }

        return $this;
    }

    public function removeParticipant(Participant $participant): self
    {
        $this->participants->removeElement($participant);

        return $this;
    }

    /** @return Collection<int, TacheSituationParticuliere> */
    public function getTaches(): Collection
    {
        return $this->taches;
    }

    public function addTache(TacheSituationParticuliere $tache): self
    {
        if (!$this->taches->contains($tache)) {
            $this->taches->add($tache);
        }

        return $this;
    }

    public function removeTache(TacheSituationParticuliere $tache): self
    {
        if (!$tache->peutEtreSupprimee()) {
            throw new \DomainException('Cette tâche ne peut pas être supprimée.');
        }
        $this->taches->removeElement($tache);

        return $this;
    }

    public function peutEtreSupprimee(): bool
    {
        return !$this->taches->exists(static fn (int $index, TacheSituationParticuliere $tache): bool => TacheSituationParticuliere::STATUT_REALISE === $tache->getStatut());
    }
}
