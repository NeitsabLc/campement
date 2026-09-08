<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commande;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CommandeTest extends WebTestCase
{
    public function testLaCommandeNecessiteUneAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/intendance/commande');

        self::assertResponseRedirects('/login');
    }

    public function testLeGestionnaireDisposeUniquementDesTroisBornesDeCommande(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $client->request('GET', '/intendance/commande');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Commande');
        self::assertSelectorCount(3, '.final-order-fields select');
        self::assertSelectorExists('select[name="repas_deduction"]');
        self::assertSelectorExists('select[name="repas_debut"][required]');
        self::assertSelectorExists('select[name="repas_fin"][required]');
        self::assertSelectorExists('select[name="repas_debut"] option', 'Petit-déjeuner');
        self::assertSelectorNotExists('input[type="checkbox"]');
    }
}
