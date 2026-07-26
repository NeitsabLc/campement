<?php

declare(strict_types=1);
namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
#[ORM\Table(name: "mouvement_stock", schema: "campement")]
#[ORM\Index(name: "idx_mouvement_stock_sejour", columns: ["sejour_id"])]
#[ORM\Index(name: "idx_mouvement_stock_date", columns: ["date_mouvement"])]
#[ORM\Index(name: "idx_mouvement_stock_groupe", columns: ["groupe_id"])]
#[ORM\Index(name: "idx_mouvement_stock_menu", columns: ["menu_id"])]
#[ORM\Index(name: "idx_mouvement_stock_utilisateur",columns: ["utilisateur_id"],),]
#[ORM\Index(name: "idx_mouvement_stock_type", columns: ["type_mouvement_id"])]
#[ORM\Index(name: "idx_mouvement_stock_origine",columns: ["origine_mouvement_id"],),]
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

    #[ORM\ManyToOne(targetEntity: TypeMouvement::class)]
    #[ORM\JoinColumn(name: "type_mouvement_id",nullable: false,onDelete: "RESTRICT",),]
    private TypeMouvement $typeMouvement;

    #[ORM\ManyToOne(targetEntity: OrigineMouvement::class)]
    #[ORM\JoinColumn(name: "origine_mouvement_id",nullable: false,onDelete: "RESTRICT",),]
    private OrigineMouvement $origineMouvement;

    #[ORM\Column(name: "date_mouvement",type: "datetimetz_immutable",options: ["default" => "CURRENT_TIMESTAMP"],),]
    private \DateTimeImmutable $dateMouvement;

    #[ORM\Column(name: "reference_document", length: 100, nullable: true)]
    private ?string $referenceDocument = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $commentaire = null;

    public function __construct(Sejour $sejour, Utilisateur $utilisateur, TypeMouvement $typeMouvement, OrigineMouvement $origineMouvement)
    {
        if ($origineMouvement->getSejour() !== $sejour) {
            throw new \InvalidArgumentException("L’origine du mouvement doit appartenir au séjour.");
        }
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
        if ($origineMouvement->getSejour() !== $this->sejour) {
            throw new \InvalidArgumentException("L’origine du mouvement doit appartenir au séjour.");
        }
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

    public function getReferenceDocument(): ?string
    {
        return $this->referenceDocument;
    }

    public function setReferenceDocument(?string $refDocument): self
    {
        $this->referenceDocument = $refDocument;
        $this->touch();
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;
        $this->touch();
        return $this;
    }
}
