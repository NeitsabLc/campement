<?php
declare(strict_types=1);
namespace App\Entity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
#[ORM\Table(name: 'presence_participant', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_presence_participant_date', columns: ['participant_id', 'date_presence'])]
class PresenceParticipant
{
    public const ABSENT = 'absent'; public const DEPART = 'depart';
    #[ORM\Id] #[ORM\Column(type: 'uuid')] private ?Uuid $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(name: 'participant_id', nullable: false, onDelete: 'CASCADE')] private Participant $participant;
    #[ORM\Column(name: 'date_presence', type: Types::DATE_IMMUTABLE)] private DateTimeImmutable $datePresence;
    #[ORM\Column(length: 10)] private string $statut;
    #[ORM\Column(length: 500, nullable: true)] private ?string $commentaire = null;
    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)] private DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)] private DateTimeImmutable $updatedAt;
    public function __construct(){ $this->id=new UuidV7(); $this->createdAt=$this->updatedAt=new DateTimeImmutable(); }
    public function getId(): ?Uuid{return $this->id;} public function getParticipant(): Participant{return $this->participant;}
    public function setParticipant(Participant $v):self{$this->participant=$v;return $this;} public function getDatePresence():DateTimeImmutable{return $this->datePresence;}
    public function setDatePresence(DateTimeImmutable $v):self{$this->datePresence=$v;return $this;} public function getStatut():string{return $this->statut;}
    public function setStatut(string $v):self{$this->statut=$v;return $this;} public function getCommentaire():?string{return $this->commentaire;}
    public function setCommentaire(?string $v):self{$this->commentaire=$v;return $this;} public function getUpdatedAt():DateTimeImmutable{return $this->updatedAt;}
    public function actualiser():self{$this->updatedAt=new DateTimeImmutable();return $this;}
}
