<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Sejour;
use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class UtilisateurTest extends TestCase
{
    public function testUnIdentifiantUuidV7EstAttribueALaCreation(): void
    {
        $utilisateur = new Utilisateur();

        self::assertNotNull($utilisateur->getId());
        self::assertInstanceOf(UuidV7::class, $utilisateur->getId());
    }

    public function testLaRelationAvecLesSejoursEstBidirectionnelleEtSansDoublon(): void
    {
        $utilisateur = new Utilisateur();
        $sejour = new Sejour(
            'Séjour de test',
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-15'),
        );

        $utilisateur->addSejourGere($sejour);
        $utilisateur->addSejourGere($sejour);

        self::assertCount(1, $utilisateur->getSejoursGeres());
        self::assertCount(1, $sejour->getGestionnaires());
        self::assertTrue($sejour->getGestionnaires()->contains($utilisateur));

        $utilisateur->removeSejourGere($sejour);

        self::assertCount(0, $utilisateur->getSejoursGeres());
        self::assertCount(0, $sejour->getGestionnaires());
    }

    public function testUnUtilisateurNePossedeQuUnRole(): void
    {
        $utilisateur = (new Utilisateur())->setRole(Utilisateur::ROLE_ADMIN);
        $utilisateur->setRole(Utilisateur::ROLE_GESTIONNAIRE);

        self::assertSame([Utilisateur::ROLE_GESTIONNAIRE], $utilisateur->getRoles());
    }

    public function testLeJetonDeReinitialisationEstHacheExpireEtUsageUnique(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->definirJetonReinitialisation('secret', new \DateTimeImmutable('+1 hour'));

        self::assertNotSame('secret', $utilisateur->getJetonReinitialisation());
        self::assertTrue($utilisateur->jetonReinitialisationEstValide('secret'));
        self::assertFalse($utilisateur->jetonReinitialisationEstValide('incorrect'));
        self::assertFalse($utilisateur->jetonReinitialisationEstValide('secret', new \DateTimeImmutable('+2 hours')));

        $utilisateur->effacerJetonReinitialisation();
        self::assertFalse($utilisateur->jetonReinitialisationEstValide('secret'));
    }

    public function testLaDesactivationDeclencheLeDelaiEtLaReactivationLAnnule(): void
    {
        $utilisateur = new Utilisateur();

        self::assertNull($utilisateur->getDesactiveAt());

        $utilisateur->setActif(false);
        $dateDesactivation = $utilisateur->getDesactiveAt();
        self::assertInstanceOf(\DateTimeImmutable::class, $dateDesactivation);

        $utilisateur->setActif(false);
        self::assertSame($dateDesactivation, $utilisateur->getDesactiveAt());

        $utilisateur->setActif(true);
        self::assertNull($utilisateur->getDesactiveAt());
    }
}
