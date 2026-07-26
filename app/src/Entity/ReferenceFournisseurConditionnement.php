<?php

declare(strict_types=1);
namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ReferenceFournisseurConditionnementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReferenceFournisseurConditionnementRepository::class,),]
#[ORM\Table(name: "denree_fournisseur_conditionnement", schema: "campement")]
#[ORM\UniqueConstraint(name: "uq_denree_fournisseur_conditionnement", columns: ["reference_fournisseur_id", "ordre"],),]
#[ORM\Index(name: "idx_denree_fournisseur_conditionnement_reference", columns: ["reference_fournisseur_id"],),]
#[ORM\Index(name: "idx_denree_fournisseur_conditionnement_unite", columns: ["unite_contenu_id"],),]
class ReferenceFournisseurConditionnement
{
    use EntityIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: ReferenceFournisseur::class)]
    #[ORM\JoinColumn(name: "reference_fournisseur_id", nullable: false, onDelete: "CASCADE",),]
    private ReferenceFournisseur $referenceFournisseur;

    #[ORM\Column(type: "smallint")]
    private int $ordre;

    #[ORM\Column(length: 50)]
    private string $libelle;

    #[ORM\Column(name: "quantite_contenu",type: "decimal",precision: 12,scale: 3,),]
    private string $quantiteContenu;

    #[ORM\Column(name: "libelle_contenu", length: 50, nullable: true)]
    private ?string $libelleContenu = null;

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(name: "unite_contenu_id", nullable: true, onDelete: "RESTRICT")]
    private ?Unite $uniteContenu = null;

    public function __construct(ReferenceFournisseur $ref, int $ordre, string $libelle, string $quantite, ?Unite $unite = null, ?string $libelleContenu = null)
    {
        if ($ordre <= 0 || (float) $quantite <= 0) {
            throw new \InvalidArgumentException("Conditionnement invalide.");
        }

        $this->initializeId();
        $this->initializeTimestamps();
        $this->referenceFournisseur = $ref;
        $this->ordre = $ordre;
        $this->libelle = $libelle;
        $this->quantiteContenu = $quantite;
        $this->uniteContenu = $unite;
        $this->libelleContenu = $libelleContenu;
    }

    public function getReferenceFournisseur(): ReferenceFournisseur
    {
        return $this->referenceFournisseur;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        if ($ordre <= 0) {
            throw new \InvalidArgumentException('Ordre invalide.');
        }
        $this->ordre = $ordre;
        $this->touch();
        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        $this->touch();
        return $this;
    }

    public function getQuantiteContenu(): string
    {
        return $this->quantiteContenu;
    }

    public function setQuantiteContenu(string $quantite): self
    {
        if ((float) $quantite <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }
        $this->quantiteContenu = $quantite;
        $this->touch();
        return $this;
    }

    public function getUniteContenu(): ?Unite
    {
        return $this->uniteContenu;
    }

    public function setUniteContenu(?Unite $unite): self
    {
        $this->uniteContenu = $unite;
        $this->touch();
        return $this;
    }

    public function getLibelleContenu(): ?string
    {
        return $this->libelleContenu;
    }

    public function setLibelleContenu(?string $libelle): self
    {
        $this->libelleContenu = $libelle;
        $this->touch();
        return $this;
    }

    public function __toString(): string
    {
        return $this->libelle;
    }
}
