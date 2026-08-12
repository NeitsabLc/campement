<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'recette_denree_quantite', schema: 'campement')]
class RecetteDenreeQuantite
{
    use EntityIdTrait;
    use TimestampableTrait;
    #[ORM\ManyToOne(inversedBy: 'quantites')] #[ORM\JoinColumn(name: 'recette_denree_id', nullable: false, onDelete: 'CASCADE')] private RecetteDenree $recetteDenree;
    #[ORM\ManyToOne] #[ORM\JoinColumn(name: 'sejour_public_cible_id', nullable: false, onDelete: 'RESTRICT')] private SejourPublicCible $sejourPublicCible;
    #[ORM\Column(name: 'quantite_individuelle', type: 'decimal', precision: 12, scale: 3)] private string $quantiteIndividuelle = '0';
    public function __construct()
    {
        $this->initializeId();
        $this->initializeTimestamps();
    }

    public function setRecetteDenree(RecetteDenree $v): self
    {
        $this->recetteDenree = $v;

        return $this;
    }

    public function getSejourPublicCible(): SejourPublicCible
    {
        return $this->sejourPublicCible;
    }

    public function setSejourPublicCible(SejourPublicCible $v): self
    {
        $this->sejourPublicCible = $v;

        return $this;
    }

    public function getQuantiteIndividuelle(): string
    {
        return $this->quantiteIndividuelle;
    }

    public function setQuantiteIndividuelle(string $v): self
    {
        $this->quantiteIndividuelle = $v;

        return $this;
    }
}
