<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Sejour;
use App\Entity\SituationParticuliere;
use App\Entity\TacheSituationParticuliere;
use PHPUnit\Framework\TestCase;

final class TacheSituationParticuliereTest extends TestCase
{
    public function testUneTacheAutomatiqueOuRealiseeNePeutPasEtreSupprimee(): void
    {
        $situation = $this->situation();
        $automatique = TacheSituationParticuliere::automatique($situation, TacheSituationParticuliere::TYPE_ACCIDENT, null);
        self::assertFalse($automatique->peutEtreSupprimee());
        $this->expectException(\DomainException::class);
        $situation->removeTache($automatique);
    }

    public function testUneTacheManuelleEstSupprimableUniquementAvantRealisation(): void
    {
        $situation = $this->situation();
        $tache = TacheSituationParticuliere::libre($situation, 'Action libre');
        self::assertTrue($tache->peutEtreSupprimee());
        $tache->setStatut(TacheSituationParticuliere::STATUT_REALISE, new \DateTimeImmutable('2026-08-12'));
        self::assertFalse($tache->peutEtreSupprimee());
        self::assertFalse($situation->peutEtreSupprimee());
    }

    public function testLeStatutRealiseRenseigneEtNettoieLaDateDeRealisation(): void
    {
        $tache = TacheSituationParticuliere::libre($this->situation(), 'Action libre');
        $tache->setStatut(TacheSituationParticuliere::STATUT_REALISE, new \DateTimeImmutable('2026-08-12'));
        self::assertSame('2026-08-12', $tache->getDateRealisation()?->format('Y-m-d'));
        $tache->setStatut(TacheSituationParticuliere::STATUT_NON_REQUIS);
        self::assertNull($tache->getDateRealisation());
    }

    private function situation(): SituationParticuliere
    {
        return new SituationParticuliere(
            new Sejour('Test', new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-31')),
            'Événement',
            new \DateTimeImmutable('2026-08-10'),
        );
    }
}
