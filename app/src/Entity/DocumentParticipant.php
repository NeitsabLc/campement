<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
#[ORM\Table(name: 'document_participant', schema: 'campement')]
#[ORM\Index(name: 'idx_document_participant', columns: ['participant_id'])]
class DocumentParticipant
{
    public const AUTORISATION_DEPART_CAMP = 'autorisation_depart_camp';
    public const FICHE_SANITAIRE = 'fiche_sanitaire';
    public const VACCINS = 'vaccins';
    public const QUALIFICATION = 'qualification';

    /** @return list<string> */
    public static function typesPour(string $typeParticipant): array
    {
        return Participant::TYPE_JEUNE === $typeParticipant
            ? [self::AUTORISATION_DEPART_CAMP, self::FICHE_SANITAIRE, self::VACCINS]
            : [self::QUALIFICATION, self::FICHE_SANITAIRE, self::VACCINS];
    }

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;
    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'participant_id', nullable: false, onDelete: 'CASCADE')]
    private Participant $participant;
    #[ORM\Column(length: 40)]
    private string $type;
    #[ORM\Column(name: 'nom_fichier', length: 255)]
    private string $nomFichier;
    #[ORM\Column(name: 'chemin_stockage', length: 500)]
    private string $cheminStockage;
    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct() { $this->id = new UuidV7(); $this->createdAt = new DateTimeImmutable(); }
    public function getId(): ?Uuid { return $this->id; }
    public function getParticipant(): Participant { return $this->participant; }
    public function setParticipant(Participant $valeur): self { $this->participant = $valeur; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $valeur): self { $this->type = $valeur; return $this; }
    public function getNomFichier(): string { return $this->nomFichier; }
    public function setNomFichier(string $valeur): self { $this->nomFichier = $valeur; return $this; }
    public function getCheminStockage(): string { return $this->cheminStockage; }
    public function setCheminStockage(string $valeur): self { $this->cheminStockage = $valeur; return $this; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
