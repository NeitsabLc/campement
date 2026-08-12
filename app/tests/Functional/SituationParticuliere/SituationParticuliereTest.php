<?php

declare(strict_types=1);

namespace App\Tests\Functional\SituationParticuliere;

use App\Entity\Sejour;
use App\Entity\SituationParticuliere;
use App\Entity\TacheSituationParticuliere;
use App\Repository\SituationParticuliereRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SituationParticuliereTest extends WebTestCase
{
    public function testLaPageNecessiteUneAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/situations-particulieres');
        self::assertResponseRedirects('/login');
    }

    public function testLeModuleDesactiveEstMasqueEtInaccessible(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $sejour = static::getContainer()->get(ContexteSejour::class)->actif();
        self::assertInstanceOf(Sejour::class, $sejour);
        $sejour->setModuleSituationsParticulieresActif(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->request('GET', '/situations-particulieres');
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextNotContains('.sidebar__nav', 'Situations particulières');
    }

    public function testUnCompteGroupeNePeutPasAccederAuModule(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'groupe@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $this->activerModule();
        $client->request('GET', '/situations-particulieres');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnGestionnairePeutCreerConsulterEtModifierUneSituation(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $this->activerModule();

        $crawler = $client->request('GET', '/situations-particulieres/nouvelle');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="situation-people"] select[data-controller="searchable-select"]');
        self::assertSelectorExists('[data-controller="situation-people"] button[data-action="situation-people#add"]');
        $form = $crawler->selectButton('Créer la situation')->form([
            'libelle' => 'Incident test fonctionnel',
            'date_situation' => '2026-07-10',
            'informations' => ['SINISTRE_MATERIEL'],
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorExists('input[name="libelle"][value="Incident test fonctionnel"]');
        self::assertSelectorTextContains('.task-cards', 'Déclaration d’accident SGDF');
        self::assertSelectorExists('input[name="date_echeance"][disabled][value="2026-07-15"]');
        self::assertSelectorExists('[data-controller="situation-task-form"] option[value=""]');
        self::assertSelectorTextContains('[data-controller="situation-task-form"] option[value=""]', 'Autre tâche');

        $situation = static::getContainer()->get(SituationParticuliereRepository::class)->findOneBy(['libelle' => 'Incident test fonctionnel']);
        self::assertInstanceOf(SituationParticuliere::class, $situation);
        self::assertCount(1, $situation->getTaches());
        $crawler = $client->request('GET', '/situations-particulieres/'.$situation->getId().'/modifier');
        self::assertSelectorTextContains('.tasks-section', 'Déclaration d’accident SGDF');
        self::assertSelectorTextContains('.tasks-section', 'À faire');
        $form = $crawler->selectButton('Enregistrer')->form([
            'libelle' => 'Incident modifié', 'date_situation' => '2026-07-11', 'informations' => [],
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/situations-particulieres/'.$situation->getId().'/modifier');
        $client->followRedirect();
        self::assertSelectorExists('input[name="libelle"][value="Incident modifié"]');
        self::assertSelectorTextContains('.task-cards', 'Non requise');
        $client->request('GET', '/situations-particulieres');
        self::assertSelectorTextContains('.task-dot', 'Acc');
    }

    public function testUneSituationAvecTacheRealiseeNePeutPasEtreSupprimee(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $sejour = $this->activerModule();
        $situation = new SituationParticuliere($sejour, 'Situation protégée', new \DateTimeImmutable('2026-07-12'));
        TacheSituationParticuliere::libre($situation, 'Tâche terminée')->setStatut(TacheSituationParticuliere::STATUT_REALISE, new \DateTimeImmutable('2026-07-12'));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($situation);
        $em->flush();

        $client->request('GET', '/situations-particulieres/'.$situation->getId().'/supprimer');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'ne peut pas être supprimée');
        self::assertSelectorNotExists('button.danger-button');
    }

    private function activerModule(): Sejour
    {
        $sejour = static::getContainer()->get(ContexteSejour::class)->actif();
        self::assertInstanceOf(Sejour::class, $sejour);
        $sejour->setModuleSituationsParticulieresActif(true);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $sejour;
    }
}
