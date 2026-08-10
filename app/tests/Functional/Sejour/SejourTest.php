<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sejour;

use App\Entity\PublicCible;
use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Entity\Groupe;
use App\Entity\Participant;
use App\Entity\DocumentParticipant;
use App\Entity\SituationParticuliere;
use App\Repository\PublicCibleRepository;
use App\Repository\SejourRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use App\Service\AnonymisationSejour;
use App\Service\StockageDocumentParticipant;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SejourTest extends WebTestCase
{
    public function testLaConfirmationDesactiveLeSejourEnUnSeulEnvoi(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $admin = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'admin@campement.local']);
        self::assertInstanceOf(Utilisateur::class, $admin);

        $sejour = new Sejour(
            'Désactivation '.bin2hex(random_bytes(4)),
            new \DateTimeImmutable('2028-08-01'),
            new \DateTimeImmutable('2028-08-10'),
        );
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($sejour);
        $entityManager->flush();
        $sejourId = (string) $sejour->getId();
        $jetonInitial = $sejour->getJetonDistributionPublique()->toRfc4122();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/sejours');
        $form = $crawler->filter('#stay-delete-'.$sejourId.' form')->form();
        self::assertSame('CONFIRMER', $form->get('confirmation')->getValue());

        $client->submit($form);
        self::assertResponseRedirects('/sejours');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'désactivé');

        $entityManager->clear();
        $sejourDesactive = $entityManager->find(Sejour::class, $sejourId);
        self::assertInstanceOf(Sejour::class, $sejourDesactive);
        self::assertFalse($sejourDesactive->isActif());

        $formulaireReactivation = $crawler->filter('form[action="/sejours/'.$sejourId.'/statut"]')->form();
        $client->submit($formulaireReactivation);
        self::assertResponseRedirects('/sejours');

        $entityManager->clear();
        $sejourReactive = $entityManager->find(Sejour::class, $sejourId);
        self::assertInstanceOf(Sejour::class, $sejourReactive);
        self::assertTrue($sejourReactive->isActif());
        self::assertNotSame($jetonInitial, $sejourReactive->getJetonDistributionPublique()->toRfc4122());
    }

    public function testAnonymisationSupprimeLesDonneesPersonnellesMaisConserveLUnite(): void
    {
        static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $sejour = new Sejour('Anonymisation '.bin2hex(random_bytes(4)), new \DateTimeImmutable('2028-07-01'), new \DateTimeImmutable('2028-07-10'));
        $groupe = (new Groupe())->setSejour($sejour)->setNom('Unité conservée')->setType('unite')
            ->setDateDebutPresence($sejour->getDateDebut())->setDateFinPresence($sejour->getDateFin());
        $participant = (new Participant())->setGroupe($groupe)->setType(Participant::TYPE_JEUNE)->setNom('Personnel')->setPrenom('Test')
            ->setDateNaissance(new \DateTimeImmutable('2015-01-01'))->setTelephoneParent1('0600000000')->setEmailParents('parents@example.test')
            ->setDateDebutPresence($sejour->getDateDebut())->setDateFinPresence($sejour->getDateFin());
        $situation = (new SituationParticuliere($sejour, 'Situation à supprimer', new \DateTimeImmutable('2028-07-02')))->addParticipant($participant);
        $temporaire = tempnam(sys_get_temp_dir(), 'anonymisation-');
        self::assertIsString($temporaire);
        file_put_contents($temporaire, '%PDF-1.4 test');
        $stockage = $container->get(StockageDocumentParticipant::class);
        $nomStockage = $stockage->stocker(new UploadedFile($temporaire, 'test.pdf', 'application/pdf', null, true));
        $document = (new DocumentParticipant())->setParticipant($participant)->setType(DocumentParticipant::FICHE_SANITAIRE)
            ->setNomFichier('test.pdf')->setCheminStockage($nomStockage);
        foreach ([$sejour, $groupe, $participant, $situation, $document] as $entite) $em->persist($entite);
        $em->flush();
        $participantId = $participant->getId(); $groupeId = $groupe->getId(); $situationId = $situation->getId();

        $container->get(AnonymisationSejour::class)->anonymiser($sejour, true);
        $em->clear();
        self::assertNull($em->find(Participant::class, $participantId));
        self::assertNull($em->find(SituationParticuliere::class, $situationId));
        self::assertInstanceOf(Groupe::class, $em->find(Groupe::class, $groupeId));
        self::assertFalse($em->find(Sejour::class, $sejour->getId())?->isActif());
        self::assertFileDoesNotExist($stockage->chemin($nomStockage));
    }

    public function testUnAdministrateurPeutDupliquerToutesLesDonneesIntendance(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $admin = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'admin@campement.local']);
        $source = $container->get(SejourRepository::class)->findOneBy(['nom' => 'Séjour de développement']);
        self::assertInstanceOf(Utilisateur::class, $admin);
        self::assertInstanceOf(Sejour::class, $source);
        $db = $container->get(Connection::class);
        $suffixe = bin2hex(random_bytes(4));
        $fournisseurId = (string) $db->fetchOne("INSERT INTO campement.fournisseur (id,sejour_id,nom,actif) VALUES (uuidv7(),:sejour,:nom,true) RETURNING id", ['sejour' => (string) $source->getId(), 'nom' => 'Fournisseur '.$suffixe]);
        $denree = $db->fetchAssociative('SELECT id,unite_inventaire_id FROM campement.denree WHERE sejour_id=:sejour ORDER BY nom LIMIT 1', ['sejour' => (string) $source->getId()]);
        self::assertIsArray($denree);
        $referenceId = (string) $db->fetchOne("INSERT INTO campement.denree_fournisseur (id,fournisseur_id,denree_id,reference,actif) VALUES (uuidv7(),:fournisseur,:denree,:reference,true) RETURNING id", ['fournisseur' => $fournisseurId, 'denree' => $denree['id'], 'reference' => 'REF-'.$suffixe]);
        $db->executeStatement("INSERT INTO campement.denree_fournisseur_conditionnement (id,reference_fournisseur_id,ordre,libelle,conditionnement_id,quantite_contenu,unite_contenu_id) VALUES (uuidv7(),:reference,1,'Inventaire',:unite,1,:unite)", ['reference' => $referenceId, 'unite' => $denree['unite_inventaire_id']]);
        $db->executeStatement("INSERT INTO campement.recette (id,sejour_id,nom,categorie,actif) VALUES (uuidv7(),:sejour,:nom,'PLAT',true)", ['sejour' => (string) $source->getId(), 'nom' => 'Recette '.$suffixe]);
        $db->executeStatement("INSERT INTO campement.menu (id,sejour_id,sejour_type_repas_id,date_menu,nom,actif) SELECT uuidv7(),:sejour,id,:date,:nom,true FROM campement.sejour_type_repas WHERE sejour_id=:sejour ORDER BY ordre LIMIT 1", ['sejour' => (string) $source->getId(), 'date' => '2026-07-'.random_int(2, 28), 'nom' => 'Menu '.$suffixe]);
        $mouvementId = (string) $db->fetchOne("INSERT INTO campement.mouvement_stock (id,sejour_id,utilisateur_id,type_mouvement_id,origine_mouvement_id,date_mouvement) SELECT uuidv7(),:sejour,:utilisateur,t.id,o.id,NOW() FROM campement.type_mouvement t CROSS JOIN campement.origine_mouvement o WHERE t.code='ENTREE' AND o.code='INVENTAIRE' RETURNING id", ['sejour' => (string) $source->getId(), 'utilisateur' => (string) $admin->getId()]);
        $db->executeStatement("INSERT INTO campement.mouvement_stock_ligne (id,mouvement_stock_id,denree_id,conditionnement_sortie_id,quantite_unite_reference,quantite_unite_inventaire) VALUES (uuidv7(),:mouvement,:denree,:unite,3,3)", ['mouvement' => $mouvementId, 'denree' => $denree['id'], 'unite' => $denree['unite_inventaire_id']]);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/sejours/'.$source->getId().'/dupliquer');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, 'input[name="duplication[]"]');
        $nom = 'Copie complète '.bin2hex(random_bytes(4));
        $form = $crawler->filter('#stay-form')->form([
            'nom' => $nom,
            'date_debut' => '2028-07-01',
            'date_fin' => '2028-07-31',
            'duplication' => ['fournisseurs', 'denrees', 'recettes', 'menus', 'inventaire'],
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/sejours');

        $cible = static::getContainer()->get(SejourRepository::class)->findOneBy(['nom' => $nom]);
        self::assertInstanceOf(Sejour::class, $cible);
        $db = static::getContainer()->get(Connection::class);
        foreach (['fournisseur', 'denree', 'recette', 'menu', 'mouvement_stock'] as $table) {
            self::assertGreaterThan(0, (int) $db->fetchOne('SELECT COUNT(*) FROM campement.'.$table.' WHERE sejour_id = :id', ['id' => (string) $cible->getId()]), $table.' doit être repris.');
        }
        self::assertGreaterThan(0, (int) $db->fetchOne('SELECT COUNT(*) FROM campement.denree_fournisseur r JOIN campement.fournisseur f ON f.id=r.fournisseur_id WHERE f.sejour_id=:id', ['id' => (string) $cible->getId()]));
        self::assertGreaterThan(0, (int) $db->fetchOne('SELECT COUNT(*) FROM campement.mouvement_stock_ligne l JOIN campement.mouvement_stock m ON m.id=l.mouvement_stock_id WHERE m.sejour_id=:id', ['id' => (string) $cible->getId()]));
    }

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
        self::assertSelectorNotExists('.sidebar__section--management[open]');
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
        self::assertSelectorNotExists('.sidebar__section--management[open]');
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
