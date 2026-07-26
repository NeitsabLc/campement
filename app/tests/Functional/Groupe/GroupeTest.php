<?php

declare(strict_types=1);

namespace App\Tests\Functional\Groupe;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GroupeTest extends WebTestCase
{
    public function testLaGestionDesGroupesNecessiteUneAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/groupes');

        self::assertResponseRedirects('/login');
    }

    public function testUnGestionnaireVoitLesGroupesEtLeFormulaireDeCreation(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)
            ->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $client->request('GET', '/groupes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Gestion des groupes');
        self::assertSelectorExists('form input[name="nom"]');
        self::assertSelectorCount(3, 'form input[name="type"]');
        self::assertSelectorExists('.edit-group-button[data-group-id]');
        self::assertSelectorExists('.delete-group-button[data-delete-url]');
        self::assertSelectorExists('.delete-group-dialog');
    }
}
