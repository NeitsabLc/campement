<?php

declare(strict_types=1);
namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\MouvementStockLigneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockLigneRepository::class)]
#[ORM\Table(name: "mouvement_stock_ligne", schema: "campement")]
#[ORM\UniqueConstraint(name: "uq_mouvement_stock_ligne_denree",columns: ["mouvement_stock_id", "denree_id"],),]
#[ORM\Index(name: "idx_mouvement_stock_ligne_mouvement",columns: ["mouvement_stock_id"],),]
#[ORM\Index(name: "idx_mouvement_stock_ligne_denree", columns: ["denree_id"])]
#[ORM\Index(name: "idx_mouvement_stock_ligne_reference_fournisseur",columns: ["reference_fournisseur_id"],),]
class MouvementStockLigne
{
    use EntityIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: MouvementStock::class)]
    #[ORM\JoinColumn(name: "mouvement_stock_id",nullable: false,onDelete: "CASCADE",),    ]
    private MouvementStock $mouvementStock;

    #[ORM\ManyToOne(targetEntity: Denree::class)]
    #[ORM\JoinColumn(name: "denree_id", nullable: false, onDelete: "RESTRICT")]
    private Denree $denree;

    #[ORM\ManyToOne(targetEntity: ReferenceFournisseur::class)]
    #[ORM\JoinColumn(name: "reference_fournisseur_id",nullable: true,onDelete: "RESTRICT",),]
    private ?ReferenceFournisseur $referenceFournisseur = null;

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(name: "conditionnement_sortie_id", nullable: true, onDelete: "RESTRICT")]
    private ?Unite $conditionnementSortie = null;

    #[ORM\Column(name: "quantite_unite_reference",type: "decimal",precision: 12,scale: 3,),]
    private string $quantiteUniteReference;

    public function __construct(MouvementStock $mouvementStock, Denree $denree, string $quantite)
    {
        if ((float) $quantite <= 0) {
            throw new \InvalidArgumentException("Quantité invalide.");
        }
        if ($denree->getSejour() !== $mouvementStock->getSejour()) {
            throw new \InvalidArgumentException('La denrée doit appartenir au séjour du mouvement.');
        }

        $this->initializeId();
        $this->initializeTimestamps();
        $this->mouvementStock = $mouvementStock;
        $this->denree = $denree;
        $this->quantiteUniteReference = $quantite;
    }

    public function getMouvementStock(): MouvementStock
    {
        return $this->mouvementStock;
    }

    public function getDenree(): Denree
    {
        return $this->denree;
    }

    public function getReferenceFournisseur(): ?ReferenceFournisseur
    {
        return $this->referenceFournisseur;
    }

    public function setReferenceFournisseur(?ReferenceFournisseur $refFournisseur): self
    {
        if ($refFournisseur !== null && $refFournisseur->getDenree() !== $this->denree) {
            throw new \InvalidArgumentException(
                "La référence ne correspond pas à la denrée.",
            );
        }
        $this->referenceFournisseur = $refFournisseur;
        $this->touch();
        return $this;
    }

    public function getConditionnementSortie(): ?Unite
    {
        return $this->conditionnementSortie;
    }

    public function setConditionnementSortie(?Unite $conditionnementSortie): self
    {
        $this->conditionnementSortie = $conditionnementSortie;
        $this->touch();

        return $this;
    }

    public function getQuantiteUniteReference(): string
    {
        return $this->quantiteUniteReference;
    }

    public function setQuantiteUniteReference(string $quantite): self
    {
        if ((float) $quantite <= 0) {
            throw new \InvalidArgumentException("Quantité invalide.");
        }
        $this->quantiteUniteReference = $quantite;
        $this->touch();
        return $this;
    }
}
