<?php

declare(strict_types=1);

namespace App\Tests\Functional\Groupe;

use App\Repository\GroupeRepository;
use App\Repository\UtilisateurRepository;
use DateTimeImmutable;
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
        self::assertSelectorTextContains('h1', 'Gestion des unités participantes');
        self::assertSelectorExists('form input[name="nom"]');
        self::assertSelectorExists('form input[name="type"][value="farfadets"]');
        self::assertSelectorExists('form input[name="type"][value="louveteaux-jeannettes"]');
        self::assertSelectorExists('form input[name="type"][value="scouts-guides"]');
        self::assertSelectorExists('form input[name="type"][value="pionniers-caravelles"]');
        self::assertSelectorExists('form input[name="type"][value="compagnons"]');
        self::assertSelectorExists('form input[name="type"][value="adulte"]');
        self::assertSelectorExists('form input[name="date_debut_presence"][type="date"]');
        self::assertSelectorExists('form input[name="date_fin_presence"][type="date"]');
        self::assertSelectorExists('.edit-group-button[data-group-id]');
        self::assertSelectorExists('.delete-group-button[data-delete-url]');
        self::assertSelectorExists('.delete-group-dialog');
        self::assertSelectorExists('.groups-heading .add-group-button');
        self::assertSelectorTextContains('.group-row dl', 'réels');
    }

    public function testUnGestionnairePeutCreerUnGroupeAdulte(): void
    {
        $nom = 'Unité adultes '.bin2hex(random_bytes(4));
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)
            ->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/groupes');
        $form = $crawler->selectButton('Créer l’unité')->form([
            'nom' => $nom,
            'type' => 'adulte',
            'effectif_jeune' => '0',
            'effectif_adulte' => '10',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/groupes');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash--success', sprintf('L’unité « %s » a bien été créée.', $nom));
        self::assertSelectorTextContains('.group-type--adulte', 'Adulte');
    }

    public function testLeRepositoryNeRetourneQueLesGroupesPresentsALaDateDemandee(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(GroupeRepository::class);
        $groupe = $repository->findActifs()[0] ?? null;
        self::assertNotNull($groupe);

        $pendantLeSejour = $repository->findActifsPresentsPourSejour(
            $groupe->getSejour(),
            new DateTimeImmutable('2026-07-15'),
        );
        $apresLeSejour = $repository->findActifsPresentsPourSejour(
            $groupe->getSejour(),
            new DateTimeImmutable('2026-08-01'),
        );

        self::assertContains($groupe, $pendantLeSejour);
        self::assertNotContains($groupe, $apresLeSejour);
    }
}
