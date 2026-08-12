<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\MouvementStockLigneConditionnementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockLigneConditionnementRepository::class, ),]
#[ORM\Table(name: 'mouvement_stock_ligne_conditionnement', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_mouvement_stock_ligne_conditionnement', columns: ['mouvement_stock_ligne_id', 'conditionnement_id'], ),]
#[ORM\Index(name: 'idx_mouvement_stock_ligne_conditionnement_ligne', columns: ['mouvement_stock_ligne_id'], ),]
#[ORM\Index(name: 'idx_mouvement_stock_ligne_conditionnement_conditionnement', columns: ['conditionnement_id'], ),]
class MouvementStockLigneConditionnement
{
    use EntityIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: MouvementStockLigne::class)]
    #[ORM\JoinColumn(name: 'mouvement_stock_ligne_id', nullable: false, onDelete: 'CASCADE', ),]
    private MouvementStockLigne $mouvementStockLigne;

    #[ORM\ManyToOne(targetEntity: ReferenceFournisseurConditionnement::class)]
    #[ORM\JoinColumn(name: 'conditionnement_id', nullable: false, onDelete: 'RESTRICT', ),]
    private ReferenceFournisseurConditionnement $conditionnement;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $quantite;

    public function __construct(MouvementStockLigne $mouvementStockLigne, ReferenceFournisseurConditionnement $conditionnement, string $quantite)
    {
        if ((float) $quantite <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }
        if ($conditionnement->getReferenceFournisseur() !== $mouvementStockLigne->getReferenceFournisseur()) {
            throw new \InvalidArgumentException('Le conditionnement ne correspond pas à la référence fournisseur de la ligne.');
        }

        $this->initializeId();
        $this->initializeTimestamps();
        $this->mouvementStockLigne = $mouvementStockLigne;
        $this->conditionnement = $conditionnement;
        $this->quantite = $quantite;
    }

    public function getMouvementStockLigne(): MouvementStockLigne
    {
        return $this->mouvementStockLigne;
    }

    public function getConditionnement(): ReferenceFournisseurConditionnement
    {
        return $this->conditionnement;
    }

    public function getQuantite(): string
    {
        return $this->quantite;
    }

    public function setQuantite(string $quantite): self
    {
        if ((float) $quantite <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }

        $this->quantite = $quantite;
        $this->touch();

        return $this;
    }
}
