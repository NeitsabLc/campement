<?php

declare(strict_types=1);

namespace App\Tests\Functional\Distribution;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DistributionTest extends WebTestCase
{
    public function testLeQrCodeChangeImmediatementApresLeRenouvellementDuLien(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $crawler = $client->request('GET', '/intendance/distribution');
        self::assertResponseIsSuccessful();
        $ancienneUrlQrCode = $crawler->filter('.distribution-qr img')->attr('src');

        $client->submit($crawler->selectButton('Générer un nouveau lien')->form());
        self::assertResponseRedirects('/intendance/distribution');
        $crawler = $client->followRedirect();

        self::assertNotSame($ancienneUrlQrCode, $crawler->filter('.distribution-qr img')->attr('src'));
    }

    public function testLeQrCodeNePeutPasEtreConserveDansLeCache(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $client->request('GET', '/intendance/distribution/qr-code');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testLeGestionnairePeutTelechargerUneArchiveDeListesDeCourses(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $client->request('GET', '/intendance/distribution/listes-courses');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/zip');
        self::assertStringContainsString('attachment;', (string) $client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testLeBoutonDesListesDeCoursesEstAfficheSurLaPageDistribution(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $crawler = $client->request('GET', '/intendance/distribution');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '/intendance/distribution/listes-courses',
            $crawler->selectLink('Télécharger les listes de course')->attr('href'),
        );
    }
}
