<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sejour;

use App\Entity\PublicCible;
use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Repository\PublicCibleRepository;
use App\Repository\SejourRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SejourTest extends WebTestCase
{
    public function testUnAdministrateurPeutCreerPuisModifierUnSejour(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $admin = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'admin@campement.local']);
        $gestionnaire = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        $public = $container->get(PublicCibleRepository::class)->findOneBy(['actif' => true]);
        self::assertInstanceOf(Utilisateur::class, $admin);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        self::assertInstanceOf(PublicCible::class, $public);
        $client->loginUser($admin);
        $nomInitial = 'Séjour test fonctionnel '.bin2hex(random_bytes(4));

        $crawler = $client->request('GET', '/sejours');
        self::assertSelectorExists('.sidebar__section--management a[href="/sejours"] + a[href="/utilisateurs"]');
        $crawler = $client->request('GET', '/sejours/ajouter');
        self::assertSelectorNotExists('#stay-form select[name="gestionnaire"]');
        $formulaireCreation = $crawler->filter('#stay-form')->form([
            'nom' => $nomInitial,
            'date_debut' => '2027-07-01',
            'date_fin' => '2027-07-15',
            'publics' => [(string) $public->getId()],
            'module_intendance' => 'on',
            'module_administratif' => 'on',
            'module_situations_particulieres' => 'on',
        ]);
        $client->submit($formulaireCreation);
        self::assertResponseRedirects('/sejours');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'créé');

        $sejour = $container->get(SejourRepository::class)->findOneBy(['nom' => $nomInitial]);
        self::assertInstanceOf(Sejour::class, $sejour);
        $crawler = $client->request('GET', '/sejours');
        $formulaireSelection = $crawler->filter('form[action="/sejours/'.$sejour->getId().'/selection"]')->form();
        $client->submit($formulaireSelection);
        self::assertResponseRedirects('/sejours');
        $crawler = $client->request('GET', '/sejours/'.$sejour->getId().'/modifier');
        $formulaireModification = $crawler->filter('#stay-form')->form([
            'nom' => 'Séjour test fonctionnel modifié',
            'date_debut' => '2027-07-02',
            'date_fin' => '2027-07-16',
            'publics' => [(string) $public->getId()],
            'module_intendance' => 'on',
            'module_administratif' => false,
            'module_situations_particulieres' => 'on',
        ]);
        $client->submit($formulaireModification);
        self::assertResponseRedirects('/sejours');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'mis à jour');

        $sejour = static::getContainer()->get(SejourRepository::class)->find($sejour->getId());
        self::assertInstanceOf(Sejour::class, $sejour);
        self::assertSame('Séjour test fonctionnel modifié', $sejour->getNom());
        self::assertTrue($sejour->isModuleIntendanceActif());
        self::assertFalse($sejour->isModuleAdministratifActif());
        self::assertTrue($sejour->isModuleSituationsParticulieresActif());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire = $entityManager->find(Utilisateur::class, $gestionnaire->getId());
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $sejour->addGestionnaire($gestionnaire);
        $entityManager->flush();

        $client->loginUser($gestionnaire);
        $crawler = $client->request('GET', '/sejours');
        self::assertSelectorTextSame('h1', 'Mes séjours');
        self::assertSelectorTextContains('.sidebar__nav', 'Mes séjours');
        self::assertSelectorTextContains('.sidebar__section--management summary', 'Gestion');
        self::assertSelectorExists('.sidebar__section--management a[href="/utilisateurs"]');
        $crawler = $client->request('GET', '/sejours/'.$sejour->getId().'/modifier');
        $formulaireGestionnaire = $crawler->filter('#stay-form')->form([
            'nom' => 'Séjour modifié par le gestionnaire',
            'date_debut' => '2027-07-03',
            'date_fin' => '2027-07-17',
            'publics' => [(string) $public->getId()],
            'module_intendance' => 'on',
            'module_administratif' => 'on',
        ]);
        $client->submit($formulaireGestionnaire);
        self::assertResponseRedirects('/sejours');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'mis à jour');
        self::assertNotNull(static::getContainer()->get(SejourRepository::class)->findOneBy(['nom' => 'Séjour modifié par le gestionnaire']));
    }
}
