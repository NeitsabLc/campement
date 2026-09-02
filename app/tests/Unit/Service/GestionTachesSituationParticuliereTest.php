<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Sejour;
use App\Entity\SituationParticuliere;
use App\Entity\TacheSituationParticuliere;
use App\Service\GestionTachesSituationParticuliere;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GestionTachesSituationParticuliereTest extends TestCase
{
    private GestionTachesSituationParticuliere $gestion;
    private SituationParticuliere $situation;

    protected function setUp(): void
    {
        $this->gestion = new GestionTachesSituationParticuliere();
        $sejour = new Sejour('Test', new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-31'));
        $this->situation = new SituationParticuliere($sejour, 'Événement', new \DateTimeImmutable('2026-08-10'));
    }

    public function testUnSinistreMaterielGenereUniquementLaDeclarationAccidentAvecLaBonneEcheance(): void
    {
        $this->situation->setInformationsComplementaires(['SINISTRE_MATERIEL']);
        $this->gestion->synchroniser($this->situation);

        self::assertCount(1, $this->situation->getTaches());
        $tache = $this->situation->getTaches()->first();
        self::assertInstanceOf(TacheSituationParticuliere::class, $tache);
        self::assertSame(TacheSituationParticuliere::TYPE_ACCIDENT, $tache->getTypePredefini());
        self::assertSame('2026-08-15', $tache->getDateEcheance()?->format('Y-m-d'));
    }

    public function testUnEvenementGraveGenereTroisTachesSansDoublonEtLesBonnesEcheances(): void
    {
        $this->situation->setInformationsComplementaires(['DECES', 'PLUSIEURS_VICTIMES']);
        $this->gestion->synchroniser($this->situation);
        $this->gestion->synchroniser($this->situation);

        self::assertCount(3, $this->situation->getTaches());
        $parType = [];
        foreach ($this->situation->getTaches() as $tache) {
            $parType[$tache->getTypePredefini()] = $tache;
        }
        self::assertSame('2026-08-15', $parType[TacheSituationParticuliere::TYPE_ACCIDENT]->getDateEcheance()?->format('Y-m-d'));
        self::assertSame('2026-08-12', $parType[TacheSituationParticuliere::TYPE_EVENEMENT_GRAVE]->getDateEcheance()?->format('Y-m-d'));
        self::assertSame('2026-08-10', $parType[TacheSituationParticuliere::TYPE_APPEL_URGENCE]->getDateEcheance()?->format('Y-m-d'));
    }

    public function testLaMaltraitanceGenereIpEtAppelUniquement(): void
    {
        $this->situation->setInformationsComplementaires(['MALTRAITANCE']);
        $this->gestion->synchroniser($this->situation);
        $types = array_map(static fn (TacheSituationParticuliere $tache): ?string => $tache->getTypePredefini(), $this->situation->getTaches()->toArray());
        sort($types);
        self::assertSame([TacheSituationParticuliere::TYPE_APPEL_URGENCE, TacheSituationParticuliere::TYPE_IP_SIGNALEMENT], $types);
        $appel = $this->situation->getTaches()->findFirst(static fn (int $index, TacheSituationParticuliere $tache): bool => TacheSituationParticuliere::TYPE_APPEL_URGENCE === $tache->getTypePredefini());
        self::assertInstanceOf(TacheSituationParticuliere::class, $appel);
        self::assertSame('2026-08-11', $appel->getDateEcheance()?->format('Y-m-d'));
    }

    public function testLaDegRendPrioritaireLeJourMemePourLAppelMemeAvecMaltraitance(): void
    {
        $this->situation->setInformationsComplementaires(['DECES', 'MALTRAITANCE']);
        $this->gestion->synchroniser($this->situation);
        $appel = $this->situation->getTaches()->findFirst(static fn (int $index, TacheSituationParticuliere $tache): bool => TacheSituationParticuliere::TYPE_APPEL_URGENCE === $tache->getTypePredefini());
        self::assertInstanceOf(TacheSituationParticuliere::class, $appel);
        self::assertSame('2026-08-10', $appel->getDateEcheance()?->format('Y-m-d'));
    }

    public function testUneTacheAutomatiqueNonRealiseeDevientNonRequisePuisRevientAFaire(): void
    {
        $this->situation->setInformationsComplementaires(['SINISTRE_MATERIEL']);
        $this->gestion->synchroniser($this->situation);
        $tache = $this->situation->getTaches()->first();
        $this->situation->setInformationsComplementaires([]);
        $this->gestion->synchroniser($this->situation);
        self::assertSame(TacheSituationParticuliere::STATUT_NON_REQUIS, $tache->getStatut());

        $this->situation->setInformationsComplementaires(['SINISTRE_MATERIEL']);
        $this->gestion->synchroniser($this->situation);
        self::assertSame(TacheSituationParticuliere::STATUT_A_FAIRE, $tache->getStatut());
        self::assertCount(1, $this->situation->getTaches());
    }

    public function testLesTachesRealiseesEtLibresNeSontPasAltereesParLeRecalcul(): void
    {
        $this->situation->setInformationsComplementaires(['SINISTRE_MATERIEL']);
        $this->gestion->synchroniser($this->situation);
        $automatique = $this->situation->getTaches()->first();
        $automatique->setStatut(TacheSituationParticuliere::STATUT_REALISE, new \DateTimeImmutable('2026-08-11'));
        $libre = TacheSituationParticuliere::libre($this->situation, 'Prévenir les familles');

        $this->situation->setInformationsComplementaires([]);
        $this->gestion->synchroniser($this->situation);

        self::assertSame(TacheSituationParticuliere::STATUT_REALISE, $automatique->getStatut());
        self::assertSame(TacheSituationParticuliere::STATUT_A_FAIRE, $libre->getStatut());
    }

    /** @param list<string> $typesAttendus */
    #[DataProvider('matriceInformations')]
    public function testChaqueInformationGenereExactementLesTypesAttendus(string $information, array $typesAttendus): void
    {
        $this->situation->setInformationsComplementaires([$information]);
        $this->gestion->synchroniser($this->situation);
        $types = array_map(static fn (TacheSituationParticuliere $tache): ?string => $tache->getTypePredefini(), $this->situation->getTaches()->toArray());
        sort($types);
        sort($typesAttendus);
        self::assertSame($typesAttendus, $types);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function matriceInformations(): iterable
    {
        $accident = TacheSituationParticuliere::TYPE_ACCIDENT;
        $grave = TacheSituationParticuliere::TYPE_EVENEMENT_GRAVE;
        $appel = TacheSituationParticuliere::TYPE_APPEL_URGENCE;
        yield 'sinistre matériel' => ['SINISTRE_MATERIEL', [$accident]];
        yield 'sinistre corporel mineur' => ['SINISTRE_CORPOREL_MINEUR', [$accident]];
        foreach (['DECES', 'HOSPITALISATION_PLUSIEURS_JOURS', 'BLESSURE_GRAVE_RISQUE_INCAPACITE', 'PLUSIEURS_VICTIMES', 'INTERVENTION_FORCES_ORDRE', 'DEPOT_PLAINTE', 'MISE_EN_PERIL_MINEURS', 'RISQUE_MEDIATIQUE'] as $information) {
            yield $information => [$information, [$accident, $grave, $appel]];
        }
        yield 'maltraitance' => ['MALTRAITANCE', [TacheSituationParticuliere::TYPE_IP_SIGNALEMENT, $appel]];
    }
}
