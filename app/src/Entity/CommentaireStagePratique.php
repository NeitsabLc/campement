<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentaireStagePratiqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: CommentaireStagePratiqueRepository::class)]
#[ORM\Table(name: 'commentaire_stage_pratique', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_commentaire_stage_participant_date', columns: ['participant_id', 'date_commentaire'])]
class CommentaireStagePratique
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'commentairesStagePratique')]
    #[ORM\JoinColumn(name: 'participant_id', nullable: false, onDelete: 'CASCADE')]
    private Participant $participant;

    #[ORM\Column(name: 'date_commentaire', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dateCommentaire;

    #[ORM\Column(type: Types::TEXT)]
    private string $commentaire;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Participant $participant, \DateTimeImmutable $dateCommentaire, string $commentaire)
    {
        $this->id = new UuidV7();
        $this->participant = $participant;
        $this->dateCommentaire = $dateCommentaire;
        $this->commentaire = $commentaire;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getParticipant(): Participant
    {
        return $this->participant;
    }

    public function getDateCommentaire(): \DateTimeImmutable
    {
        return $this->dateCommentaire;
    }

    public function getCommentaire(): string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): self
    {
        $this->commentaire = $commentaire;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
