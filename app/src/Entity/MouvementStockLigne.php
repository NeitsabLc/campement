<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\MouvementStockLigneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockLigneRepository::class)]
#[ORM\Table(name: 'mouvement_stock_ligne', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_mouvement_stock_ligne_denree', columns: ['mouvement_stock_id', 'denree_id'], ),]
#[ORM\Index(name: 'idx_mouvement_stock_ligne_mouvement', columns: ['mouvement_stock_id'], ),]
#[ORM\Index(name: 'idx_mouvement_stock_ligne_denree', columns: ['denree_id'])]
#[ORM\Index(name: 'idx_mouvement_stock_ligne_reference_fournisseur', columns: ['reference_fournisseur_id'], ),]
#[ORM\Index(name: 'idx_mouvement_stock_ligne_conditionnement_saisie', columns: ['conditionnement_saisie_id'])]
class MouvementStockLigne
{
    use EntityIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: MouvementStock::class)]
    #[ORM\JoinColumn(name: 'mouvement_stock_id', nullable: false, onDelete: 'CASCADE', ),]
    private MouvementStock $mouvementStock;

    #[ORM\ManyToOne(targetEntity: Denree::class)]
    #[ORM\JoinColumn(name: 'denree_id', nullable: false, onDelete: 'RESTRICT')]
    private Denree $denree;

    #[ORM\ManyToOne(targetEntity: ReferenceFournisseur::class)]
    #[ORM\JoinColumn(name: 'reference_fournisseur_id', nullable: true, onDelete: 'RESTRICT', ),]
    private ?ReferenceFournisseur $referenceFournisseur = null;

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(name: 'conditionnement_saisie_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Unite $conditionnementSaisie = null;

    #[ORM\Column(name: 'quantite_saisie', type: 'decimal', precision: 12, scale: 3, nullable: true, options: ['comment' => 'Quantité brute saisie par l’utilisateur dans conditionnement_saisie_id ; NULL pour une ligne détaillée par niveaux fournisseur.'])]
    private ?string $quantiteSaisie;

    #[ORM\Column(name: 'numero_lot', type: 'string', length: 100, nullable: true, options: ['comment' => 'Numéro de lot relevé sur la denrée lors de son entrée en stock.'])]
    private ?string $numeroLot = null;

    public function __construct(MouvementStock $mouvementStock, Denree $denree, ?string $quantiteSaisie)
    {
        if (null !== $quantiteSaisie && (float) $quantiteSaisie <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }
        if ($denree->getSejour() !== $mouvementStock->getSejour()) {
            throw new \InvalidArgumentException('La denrée doit appartenir au séjour du mouvement.');
        }

        $this->initializeId();
        $this->initializeTimestamps();
        $this->mouvementStock = $mouvementStock;
        $this->denree = $denree;
        $this->quantiteSaisie = $quantiteSaisie;
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
        if (null !== $refFournisseur && $refFournisseur->getDenree() !== $this->denree) {
            throw new \InvalidArgumentException('La référence ne correspond pas à la denrée.');
        }
        $this->referenceFournisseur = $refFournisseur;
        $this->touch();

        return $this;
    }

    public function getConditionnementSaisie(): ?Unite
    {
        return $this->conditionnementSaisie;
    }

    public function setConditionnementSaisie(?Unite $conditionnementSaisie): self
    {
        $this->conditionnementSaisie = $conditionnementSaisie;
        $this->touch();

        return $this;
    }

    public function getQuantiteSaisie(): ?string
    {
        return $this->quantiteSaisie;
    }

    public function setQuantiteSaisie(?string $quantite): self
    {
        if (null !== $quantite && (float) $quantite <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }
        $this->quantiteSaisie = $quantite;
        $this->touch();

        return $this;
    }

    public function getNumeroLot(): ?string
    {
        return $this->numeroLot;
    }

    public function setNumeroLot(?string $numeroLot): self
    {
        $numeroLot = null === $numeroLot ? null : trim($numeroLot);
        if ('' === $numeroLot) {
            $numeroLot = null;
        }
        if (null !== $numeroLot && mb_strlen($numeroLot) > 100) {
            throw new \InvalidArgumentException('Le numéro de lot ne peut pas dépasser 100 caractères.');
        }
        $this->numeroLot = $numeroLot;
        $this->touch();

        return $this;
    }
}
