<?php

declare(strict_types=1);

namespace App\Tests\Functional\Participant;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExportTest extends WebTestCase
{
    public function testLaPageNecessiteUneAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/administratif/export');
        self::assertResponseRedirects('/login');
    }

    public function testGestionnaireEtAdministrateurAccedentAuxTroisExports(): void
    {
        $client = static::createClient();
        foreach (['gestionnaire@campement.local', 'admin@campement.local'] as $email) {
            $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => $email]);
            self::assertNotNull($utilisateur);
            $client->loginUser($utilisateur);
            $client->request('GET', '/administratif/export');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Export');
            self::assertSelectorCount(3, '.export-card');
            self::assertSelectorExists('a[href="/administratif/export/documents/adultes"]');
            self::assertSelectorExists('a[href="/administratif/export/documents/jeunes"]');
            self::assertSelectorExists('a[href="/administratif/export/participants"]');
        }
    }

    public function testLesExportsSontDesPdfTelechargeables(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        foreach (['/administratif/export/documents/adultes', '/administratif/export/documents/jeunes', '/administratif/export/participants'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('content-type', 'application/pdf');
            self::assertStringContainsString('attachment; filename="', (string) $client->getResponse()->headers->get('content-disposition'));
            self::assertStringStartsWith('%PDF-', (string) $client->getResponse()->getContent());
        }
    }

    public function testUnUtilisateurGroupeNePeutPasAccederAuxExports(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'groupe@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $client->request('GET', '/administratif/export');
        self::assertResponseStatusCodeSame(403);
    }
}
