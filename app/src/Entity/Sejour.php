<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\ActivableTrait;
use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\SejourRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: SejourRepository::class)]
#[ORM\Table(name: 'sejour', schema: 'campement')]
#[ORM\Index(name: 'idx_sejour_date', columns: ['date_debut'])]
class Sejour
{
    use EntityIdTrait;
    use TimestampableTrait;
    use ActivableTrait;

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\Column(name: 'date_debut', type: 'date_immutable')]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column(name: 'date_fin', type: 'date_immutable')]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(name: 'module_intendance_actif', options: ['default' => true])]
    private bool $moduleIntendanceActif = true;

    #[ORM\Column(name: 'module_administratif_actif', options: ['default' => true])]
    private bool $moduleAdministratifActif = true;

    #[ORM\Column(name: 'module_situations_particulieres_actif', options: ['default' => false])]
    private bool $moduleSituationsParticulieresActif = false;

    #[ORM\Column(name: 'distribution_publique_active', options: ['default' => true])]
    private bool $distributionPubliqueActive = true;

    #[ORM\Column(name: 'distribuer_gouter_dejeuner', options: ['default' => false])]
    private bool $distribuerGouterDejeuner = false;

    #[ORM\Column(name: 'anonymise_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $anonymiseAt = null;

    #[ORM\Column(name: 'jeton_distribution_publique', type: 'uuid', unique: true)]
    private Uuid $jetonDistributionPublique;

    /** @var Collection<int, Utilisateur> */
    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: 'sejoursGeres')]
    private Collection $gestionnaires;

    /** @var Collection<int, SejourPublicCible> */
    #[ORM\OneToMany(mappedBy: 'sejour', targetEntity: SejourPublicCible::class, cascade: ['persist'])]
    private Collection $publicsCibles;

    public function __construct(string $nom, \DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin)
    {
        if ($dateFin < $dateDebut) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure ou égale à la date de début.');
        }
        $this->initializeId();
        $this->initializeTimestamps();
        $this->nom = $nom;
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->gestionnaires = new ArrayCollection();
        $this->publicsCibles = new ArrayCollection();
        $this->jetonDistributionPublique = new UuidV7();
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

    public function getDateDebut(): \DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): self
    {
        if ($this->dateFin < $dateDebut) {
            throw new \InvalidArgumentException('Dates invalides.');
        }

        $this->dateDebut = $dateDebut;
        $this->touch();

        return $this;
    }

    public function getDateFin(): \DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeImmutable $dateFin): self
    {
        if ($dateFin < $this->dateDebut) {
            throw new \InvalidArgumentException('Dates invalides.');
        }

        $this->dateFin = $dateFin;
        $this->touch();

        return $this;
    }

    public function setDates(\DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin): self
    {
        if ($dateFin < $dateDebut) {
            throw new \InvalidArgumentException('Dates invalides.');
        }
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->touch();

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): self
    {
        $this->lieu = $lieu;
        $this->touch();

        return $this;
    }

    public function isModuleIntendanceActif(): bool
    {
        return $this->moduleIntendanceActif;
    }

    public function setModuleIntendanceActif(bool $actif): self
    {
        $this->moduleIntendanceActif = $actif;
        $this->touch();

        return $this;
    }

    public function isModuleAdministratifActif(): bool
    {
        return $this->moduleAdministratifActif;
    }

    public function setModuleAdministratifActif(bool $actif): self
    {
        $this->moduleAdministratifActif = $actif;
        $this->touch();

        return $this;
    }

    public function isModuleSituationsParticulieresActif(): bool
    {
        return $this->moduleSituationsParticulieresActif;
    }

    public function setModuleSituationsParticulieresActif(bool $actif): self
    {
        $this->moduleSituationsParticulieresActif = $actif;
        $this->touch();

        return $this;
    }

    public function isDistributionPubliqueActive(): bool
    {
        return $this->distributionPubliqueActive;
    }

    public function setDistributionPubliqueActive(bool $actif): self
    {
        $this->distributionPubliqueActive = $actif;
        $this->touch();

        return $this;
    }

    public function isDistributionPubliqueOuverte(?\DateTimeImmutable $date = null): bool
    {
        $date = ($date ?? new \DateTimeImmutable('today'))->setTime(0, 0);

        return $this->distributionPubliqueActive && $this->dateFin >= $date;
    }

    public function isDistribuerGouterDejeuner(): bool
    {
        return $this->distribuerGouterDejeuner;
    }

    public function setDistribuerGouterDejeuner(bool $valeur): self
    {
        $this->distribuerGouterDejeuner = $valeur;
        $this->touch();

        return $this;
    }

    public function getJetonDistributionPublique(): Uuid
    {
        return $this->jetonDistributionPublique;
    }

    public function renouvelerJetonDistributionPublique(): self
    {
        $this->jetonDistributionPublique = new UuidV7();
        $this->touch();

        return $this;
    }

    public function getAnonymiseAt(): ?\DateTimeImmutable
    {
        return $this->anonymiseAt;
    }

    public function marquerAnonymise(?\DateTimeImmutable $date = null): self
    {
        $this->anonymiseAt = $date ?? new \DateTimeImmutable();
        $this->touch();

        return $this;
    }

    public function reinitialiserAnonymisation(): self
    {
        $this->anonymiseAt = null;
        $this->touch();

        return $this;
    }

    /** @return Collection<int, SejourPublicCible> */
    public function getPublicsCibles(): Collection
    {
        return $this->publicsCibles;
    }

    public function addPublicCible(PublicCible $publicCible): self
    {
        foreach ($this->publicsCibles as $association) {
            if ($association->getPublicCible() === $publicCible) {
                $association->setActif(true);

                return $this;
            }
        }
        $this->publicsCibles->add(new SejourPublicCible($this, $publicCible));

        return $this;
    }

    public function removePublicCible(PublicCible $publicCible): self
    {
        foreach ($this->publicsCibles as $association) {
            if ($association->getPublicCible() === $publicCible) {
                $association->setActif(false);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getGestionnaires(): Collection
    {
        return $this->gestionnaires;
    }

    public function addGestionnaire(Utilisateur $utilisateur): self
    {
        if (!$this->gestionnaires->contains($utilisateur)) {
            $this->gestionnaires->add($utilisateur);
            $utilisateur->addSejourGere($this);
        }

        return $this;
    }

    public function removeGestionnaire(Utilisateur $utilisateur): self
    {
        if ($this->gestionnaires->removeElement($utilisateur)) {
            $utilisateur->removeSejourGere($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
