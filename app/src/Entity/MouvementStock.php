<?php

declare(strict_types=1);
namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
#[ORM\Table(name: "mouvement_stock", schema: "campement")]
#[ORM\UniqueConstraint(name: 'uq_mouvement_stock_cle_soumission', columns: ['cle_soumission'])]
#[ORM\Index(name: "idx_mouvement_stock_sejour", columns: ["sejour_id"])]
#[ORM\Index(name: "idx_mouvement_stock_date", columns: ["date_mouvement"])]
#[ORM\Index(name: "idx_mouvement_stock_groupe", columns: ["groupe_id"])]
#[ORM\Index(name: "idx_mouvement_stock_menu", columns: ["menu_id"])]
#[ORM\Index(name: "idx_mouvement_stock_utilisateur",columns: ["utilisateur_id"],),]
#[ORM\Index(name: "idx_mouvement_stock_type", columns: ["type_mouvement_id"])]
#[ORM\Index(name: "idx_mouvement_stock_origine",columns: ["origine_mouvement_id"],),]
#[ORM\Index(name: 'idx_mouvement_stock_annule', columns: ['annule_at'])]
#[ORM\Index(name: 'idx_mouvement_stock_annule_par', columns: ['annule_par_id'])]
class MouvementStock
{
    use EntityIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Sejour::class)]
    #[ORM\JoinColumn(name: "sejour_id", nullable: false, onDelete: "CASCADE")]
    private Sejour $sejour;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "utilisateur_id",nullable: false,onDelete: "RESTRICT",),]
    private Utilisateur $utilisateur;

    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(name: "groupe_id", nullable: true, onDelete: "RESTRICT")]
    private ?Groupe $groupe = null;

    #[ORM\ManyToOne(targetEntity: Menu::class)]
    #[ORM\JoinColumn(name: "menu_id", nullable: true, onDelete: "RESTRICT")]
    private ?Menu $menu = null;

    #[ORM\Column(name: 'cle_soumission', type: 'uuid', nullable: true)]
    private ?Uuid $cleSoumission = null;

    #[ORM\Column(name: 'annule_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $annuleAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'annule_par_id', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $annulePar = null;

    #[ORM\Column(name: 'motif_annulation', type: 'text', nullable: true)]
    private ?string $motifAnnulation = null;

    #[ORM\ManyToOne(targetEntity: TypeMouvement::class)]
    #[ORM\JoinColumn(name: "type_mouvement_id",nullable: false,onDelete: "RESTRICT",),]
    private TypeMouvement $typeMouvement;

    #[ORM\ManyToOne(targetEntity: OrigineMouvement::class)]
    #[ORM\JoinColumn(name: "origine_mouvement_id",nullable: false,onDelete: "RESTRICT",),]
    private OrigineMouvement $origineMouvement;

    #[ORM\Column(name: "date_mouvement", type: "datetimetz_immutable", options: ["default" => new \Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp()])]
    private \DateTimeImmutable $dateMouvement;

    public function __construct(Sejour $sejour, Utilisateur $utilisateur, TypeMouvement $typeMouvement, OrigineMouvement $origineMouvement)
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->sejour = $sejour;
        $this->utilisateur = $utilisateur;
        $this->typeMouvement = $typeMouvement;
        $this->origineMouvement = $origineMouvement;
        $this->dateMouvement = new \DateTimeImmutable();
    }

    public function getSejour(): Sejour
    {
        return $this->sejour;
    }

    public function getUtilisateur(): Utilisateur
    {
        return $this->utilisateur;
    }

    public function getGroupe(): ?Groupe
    {
        return $this->groupe;
    }

    public function setGroupe(?Groupe $groupe): self
    {
        $this->groupe = $groupe;
        $this->touch();
        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): self
    {
        $this->menu = $menu;
        $this->touch();
        return $this;
    }

    public function getCleSoumission(): ?Uuid
    {
        return $this->cleSoumission;
    }

    public function setCleSoumission(?Uuid $cleSoumission): self
    {
        $this->cleSoumission = $cleSoumission;

        return $this;
    }

    public function isAnnule(): bool
    {
        return null !== $this->annuleAt;
    }

    public function getAnnuleAt(): ?\DateTimeImmutable
    {
        return $this->annuleAt;
    }

    public function getAnnulePar(): ?Utilisateur
    {
        return $this->annulePar;
    }

    public function getMotifAnnulation(): ?string
    {
        return $this->motifAnnulation;
    }

    public function annuler(Utilisateur $utilisateur, string $motif): self
    {
        $motif = trim($motif);
        if ('' === $motif) {
            throw new \InvalidArgumentException('Le motif d’annulation est obligatoire.');
        }
        if ($this->isAnnule()) {
            throw new \LogicException('Ce mouvement est déjà annulé.');
        }

        $this->annuleAt = new \DateTimeImmutable();
        $this->annulePar = $utilisateur;
        $this->motifAnnulation = $motif;
        $this->touch();

        return $this;
    }

    public function getTypeMouvement(): TypeMouvement
    {
        return $this->typeMouvement;
    }

    public function setTypeMouvement(TypeMouvement $typeMouvement): self
    {
        $this->typeMouvement = $typeMouvement;
        $this->touch();
        return $this;
    }

    public function getOrigineMouvement(): OrigineMouvement
    {
        return $this->origineMouvement;
    }

    public function setOrigineMouvement(OrigineMouvement $origineMouvement): self
    {
        $this->origineMouvement = $origineMouvement;
        $this->touch();
        return $this;
    }

    public function getDateMouvement(): \DateTimeImmutable
    {
        return $this->dateMouvement;
    }

    public function setDateMouvement(\DateTimeImmutable $date): self
    {
        $this->dateMouvement = $date;
        $this->touch();
        return $this;
    }

}
