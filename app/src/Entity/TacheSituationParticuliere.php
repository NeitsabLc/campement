<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\EntityIdTrait;
use App\Entity\Traits\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tache_situation_particuliere', schema: 'campement')]
#[ORM\UniqueConstraint(name: 'uq_tache_situation_particuliere_type', columns: ['situation_particuliere_id', 'type_predefini'])]
#[ORM\Index(name: 'idx_tache_situation_particuliere_situation', columns: ['situation_particuliere_id'])]
#[ORM\Index(name: 'idx_tache_situation_particuliere_statut_echeance', columns: ['statut', 'date_echeance'])]
class TacheSituationParticuliere
{
    public const COMMENTAIRE_LONGUEUR_MAX = 2000;

    use EntityIdTrait;
    use TimestampableTrait;

    public const TYPE_ACCIDENT = 'DECLARATION_ACCIDENT_SGDF';
    public const TYPE_EVENEMENT_GRAVE = 'DECLARATION_EVENEMENT_GRAVE';
    public const TYPE_IP_SIGNALEMENT = 'IP_OU_SIGNALEMENT';
    public const TYPE_APPEL_URGENCE = 'APPEL_LIGNE_URGENCE';
    public const TYPES = [
        self::TYPE_ACCIDENT => 'Déclaration d’accident SGDF',
        self::TYPE_EVENEMENT_GRAVE => 'Déclaration d’événement grave',
        self::TYPE_IP_SIGNALEMENT => 'IP ou signalement',
        self::TYPE_APPEL_URGENCE => 'Appel à la ligne d’urgence',
    ];
    public const ORIGINE_AUTOMATIQUE = 'AUTOMATIQUE';
    public const ORIGINE_MANUELLE = 'MANUELLE_PREDEFINIE';
    public const ORIGINE_LIBRE = 'LIBRE';
    public const STATUT_A_FAIRE = 'A_FAIRE';
    public const STATUT_REALISE = 'REALISE';
    public const STATUT_NON_REQUIS = 'NON_REQUIS';
    public const STATUTS = [self::STATUT_A_FAIRE => 'À faire', self::STATUT_REALISE => 'Réalisée', self::STATUT_NON_REQUIS => 'Non requise'];

    #[ORM\ManyToOne(inversedBy: 'taches')]
    #[ORM\JoinColumn(name: 'situation_particuliere_id', nullable: false, onDelete: 'CASCADE')]
    private SituationParticuliere $situation;
    #[ORM\Column(name: 'type_predefini', length: 40, nullable: true)] private ?string $typePredefini = null;
    #[ORM\Column(name: 'libelle_libre', length: 200, nullable: true)] private ?string $libelleLibre = null;
    #[ORM\Column(length: 25)] private string $origine;
    #[ORM\Column(length: 15)] private string $statut = self::STATUT_A_FAIRE;
    #[ORM\Column(name: 'date_echeance', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $dateEcheance = null;
    #[ORM\Column(name: 'date_realisation', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $dateRealisation = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)] private ?string $commentaire = null;

    private function __construct(SituationParticuliere $situation, string $origine)
    {
        $this->initializeId();
        $this->initializeTimestamps();
        $this->situation = $situation;
        $this->origine = $origine;
        $situation->addTache($this);
    }
    public static function automatique(SituationParticuliere $situation, string $type, ?\DateTimeImmutable $echeance): self
    {
        $tache = new self($situation, self::ORIGINE_AUTOMATIQUE);
        $tache->definirType($type);
        $tache->dateEcheance = $echeance;
        return $tache;
    }
    public static function manuellePredefinie(SituationParticuliere $situation, string $type, ?\DateTimeImmutable $echeance = null): self
    {
        $tache = new self($situation, self::ORIGINE_MANUELLE);
        $tache->definirType($type);
        $tache->dateEcheance = $echeance;
        return $tache;
    }
    public static function libre(SituationParticuliere $situation, string $libelle, ?\DateTimeImmutable $echeance = null): self
    {
        $libelle = trim($libelle);
        if ('' === $libelle || mb_strlen($libelle) > 200) throw new \InvalidArgumentException('Le libellé libre est obligatoire et limité à 200 caractères.');
        $tache = new self($situation, self::ORIGINE_LIBRE);
        $tache->libelleLibre = $libelle;
        $tache->dateEcheance = $echeance;
        return $tache;
    }
    private function definirType(string $type): void
    {
        if (!isset(self::TYPES[$type])) throw new \InvalidArgumentException('Le type de tâche est invalide.');
        $this->typePredefini = $type;
    }
    public function getSituation(): SituationParticuliere { return $this->situation; }
    public function getTypePredefini(): ?string { return $this->typePredefini; }
    public function getLibelle(): string { return null !== $this->typePredefini ? self::TYPES[$this->typePredefini] : (string) $this->libelleLibre; }
    public function getOrigine(): string { return $this->origine; }
    public function getStatut(): string { return $this->statut; }
    public function getDateEcheance(): ?\DateTimeImmutable { return $this->dateEcheance; }
    public function getDateRealisation(): ?\DateTimeImmutable { return $this->dateRealisation; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): self
    {
        $commentaire = null === $commentaire || '' === trim($commentaire) ? null : trim($commentaire);
        if (null !== $commentaire && mb_strlen($commentaire) > self::COMMENTAIRE_LONGUEUR_MAX) {
            throw new \InvalidArgumentException('Le commentaire ne peut pas dépasser 2 000 caractères.');
        }
        $this->commentaire = $commentaire;
        $this->touch();
        return $this;
    }
    public function setDateEcheance(?\DateTimeImmutable $date): self { $this->dateEcheance = $date; $this->touch(); return $this; }
    public function setStatut(string $statut, ?\DateTimeImmutable $dateRealisation = null): self
    {
        if (!isset(self::STATUTS[$statut])) throw new \InvalidArgumentException('Le statut est invalide.');
        if (self::STATUT_REALISE === $statut && null === $dateRealisation) $dateRealisation = new \DateTimeImmutable('today');
        $this->statut = $statut;
        $this->dateRealisation = self::STATUT_REALISE === $statut ? $dateRealisation : null;
        $this->touch();
        return $this;
    }
    public function estEnRetard(?\DateTimeImmutable $aujourdhui = null): bool
    {
        $aujourdhui ??= new \DateTimeImmutable('today');
        return self::STATUT_A_FAIRE === $this->statut && null !== $this->dateEcheance && $this->dateEcheance < $aujourdhui;
    }
    public function peutEtreSupprimee(): bool
    {
        return self::ORIGINE_AUTOMATIQUE !== $this->origine && self::STATUT_REALISE !== $this->statut;
    }
}
