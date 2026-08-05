<?php

declare(strict_types=1);

namespace App\Tests\Functional\Participant;

use App\Repository\GroupeRepository;
use App\Repository\ParticipantRepository;
use App\Entity\Sejour;
use App\Entity\Participant;
use App\Entity\DocumentParticipant;
use DateTimeImmutable;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ParticipantTest extends WebTestCase
{
    public function testLaPageNecessiteUneAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/administratif/participants');
        self::assertResponseRedirects('/login');
    }

    public function testUnGestionnaireVoitLesParticipantsParGroupe(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $client->request('GET', '/administratif/participants');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Participants');
        self::assertSelectorExists('.participant-group-card');
        self::assertSelectorExists('input[name="telephone_parent_1"]');
        self::assertSelectorExists('input[name="qualifications[]"][value="BAFA"]');
        self::assertSelectorExists('input[name="stagiaire_bafa"]');
    }

    public function testLeFormulaireDAjoutReprendLesDatesDePresenceDuGroupe(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $groupe = static::getContainer()->get(GroupeRepository::class)->findActifs()[0] ?? null;
        self::assertNotNull($groupe);

        $client->request('GET', sprintf('/administratif/participants/ajouter?groupe=%s&type=jeune', $groupe->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('select[name="groupe_id"] option[value="%s"][selected]', $groupe->getId()));
        self::assertSelectorExists(sprintf('input[name="date_debut_presence"][value="%s"]', $groupe->getDateDebutPresence()->format('Y-m-d')));
        self::assertSelectorExists(sprintf('input[name="date_fin_presence"][value="%s"]', $groupe->getDateFinPresence()->format('Y-m-d')));
    }

    public function testLeModuleDesactiveEstMasqueEtSaPageInaccessible(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $sejour = static::getContainer()->get(ContexteSejour::class)->actif();
        self::assertNotNull($sejour);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $sejourId = $sejour->getId();

        try {
            $sejour->setModuleAdministratifActif(false);
            $entityManager->flush();
            $client->request('GET', '/administratif/participants');
            self::assertResponseRedirects('/');
            $client->followRedirect();
            self::assertSelectorTextNotContains('.sidebar__nav', 'Administratif');
            self::assertSelectorTextContains('.flash--error', 'Ce module n’est pas actif');
        } finally {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $sejourAReinitialiser = $entityManager->find(Sejour::class, $sejourId);
            self::assertNotNull($sejourAReinitialiser);
            $sejourAReinitialiser->setModuleAdministratifActif(true);
            $entityManager->flush();
        }
    }

    public function testUnGestionnairePeutAjouterUnJeune(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $groupe = static::getContainer()->get(GroupeRepository::class)->findActifs()[0] ?? null;
        self::assertNotNull($groupe);
        $crawler = $client->request('GET', '/administratif/participants');
        $form = $crawler->selectButton('Ajouter le participant')->form([
            'type' => 'jeune', 'groupe_id' => (string) $groupe->getId(), 'nom' => 'Martin', 'prenom' => 'Camille',
            'date_naissance' => '2013-05-12', 'telephone_parent_1' => '0601020304',
            'email_parents' => 'parents@example.test', 'date_debut_presence' => '2026-07-10', 'date_fin_presence' => '2026-07-24',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/administratif/participants');
    }

    public function testUnGestionnairePeutAjouterUnAdulteAvecSesQualifications(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $groupe = static::getContainer()->get(GroupeRepository::class)->findActifs()[0] ?? null;
        self::assertNotNull($groupe);
        $crawler = $client->request('GET', '/administratif/participants');
        $form = $crawler->selectButton('Ajouter le participant')->form([
            'type' => 'adulte', 'groupe_id' => (string) $groupe->getId(), 'nom' => 'Durand', 'prenom' => 'Alex',
            'date_naissance' => '1995-02-18', 'contact_urgence_nom_prenom' => 'Sam Durand',
            'contact_urgence_telephone' => '0600000000',
            'telephone' => '0611223344', 'email' => 'alex.durand@example.test',
            'qualifications' => ['BAFD'], 'stagiaire_bafa' => '1',
            'date_debut_presence' => '2026-07-10', 'date_fin_presence' => '2026-07-24',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/administratif/participants');
    }

    public function testLesContactsEtLesDatesDePresenceSontControlesCoteServeur(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $groupe = static::getContainer()->get(GroupeRepository::class)->findActifs()[0] ?? null;
        self::assertNotNull($groupe);
        $crawler = $client->request('GET', '/administratif/participants');
        $form = $crawler->selectButton('Ajouter le participant')->form([
            'type' => 'jeune', 'groupe_id' => (string) $groupe->getId(), 'nom' => 'Test', 'prenom' => 'Validation',
            'date_naissance' => '2013-05-12', 'telephone_parent_1' => '123456',
            'email_parents' => 'adresse-invalide', 'date_debut_presence' => '2027-01-01', 'date_fin_presence' => '2027-01-10',
        ]);
        $client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.distribution-errors', 'numéro de téléphone des parents est invalide');
        self::assertSelectorTextContains('.distribution-errors', 'adresse e-mail des parents est invalide');
        self::assertSelectorTextContains('.distribution-errors', 'date de début de présence doit être comprise');
        self::assertSelectorTextContains('.distribution-errors', 'date de fin de présence doit être comprise');
    }

    public function testUnGestionnairePeutModifierUneFicheParticipant(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $groupe = static::getContainer()->get(GroupeRepository::class)->findActifs()[0] ?? null;
        self::assertNotNull($groupe);
        $participant = (new Participant())->setGroupe($groupe)->setType(Participant::TYPE_JEUNE)
            ->setNom('Avant')->setPrenom('Modification')->setDateNaissance(new DateTimeImmutable('2013-05-12'))
            ->setTelephoneParent1('0601020304')->setEmailParents('parents@example.test')
            ->setDateDebutPresence(new DateTimeImmutable('2026-07-10'))->setDateFinPresence(new DateTimeImmutable('2026-07-24'));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($participant);
        $entityManager->flush();

        $crawler = $client->request('GET', '/administratif/participants/'.$participant->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('legend', 'Informations');
        self::assertSelectorTextContains('.participant-edit-form fieldset:nth-of-type(2) legend', 'Contact des parents');
        $form = $crawler->selectButton('Enregistrer les modifications')->form(['nom' => 'Après']);
        $client->submit($form);
        self::assertResponseRedirects('/administratif/participants/'.$participant->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'a bien été mise à jour');
        self::assertSelectorTextContains('h1', 'Modification Après');
    }

    public function testUnGestionnairePeutSupprimerUneFicheParticipant(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $groupe = static::getContainer()->get(GroupeRepository::class)->findActifs()[0] ?? null;
        self::assertNotNull($groupe);
        $participant = (new Participant())->setGroupe($groupe)->setType(Participant::TYPE_JEUNE)
            ->setNom('À supprimer')->setPrenom('Fiche')->setDateNaissance(new DateTimeImmutable('2013-05-12'))
            ->setTelephoneParent1('0601020304')->setEmailParents('parents@example.test')
            ->setDateDebutPresence(new DateTimeImmutable('2026-07-10'))->setDateFinPresence(new DateTimeImmutable('2026-07-24'));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($participant);
        $entityManager->flush();
        $id = (string) $participant->getId();

        $crawler = $client->request('GET', '/administratif/participants');
        $bouton = $crawler->filter('.delete-participant-button[data-delete-url$="/'.$id.'/supprimer"]');
        self::assertCount(1, $bouton);
        $client->request('POST', '/administratif/participants/'.$id.'/supprimer', ['_token' => $bouton->attr('data-delete-token')]);
        self::assertResponseRedirects('/administratif/participants');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'a bien été supprimée');
        self::assertNull(static::getContainer()->get(EntityManagerInterface::class)->find(Participant::class, $id));
    }

    public function testUnGestionnairePeutTelechargerLaListeEnPdf(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $client->request('GET', '/administratif/participants/pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertStringContainsString('attachment; filename="participants-', (string) $client->getResponse()->headers->get('content-disposition'));
        self::assertStringStartsWith('%PDF-', $client->getResponse()->getContent());
    }

    public function testUnUtilisateurGroupeNeVoitQueSonUniteEtPeutYAjouterUnParticipant(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'groupe@campement.local']);
        self::assertNotNull($utilisateur);
        self::assertNotNull($utilisateur->getGroupe());
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/administratif/participants');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.participant-group-card');
        self::assertSelectorTextContains('.participant-group-card h2', $utilisateur->getGroupe()->getNom());
        self::assertSelectorNotExists('.participants-pdf-button');
        self::assertSelectorExists('.edit-participant-button');
        self::assertSelectorNotExists('.delete-participant-button');

        $form = $crawler->selectButton('Ajouter le participant')->form([
            'type' => 'jeune', 'groupe_id' => (string) $utilisateur->getGroupe()->getId(),
            'nom' => 'Groupe', 'prenom' => 'Ajout', 'date_naissance' => '2013-05-12',
            'telephone_parent_1' => '0601020304', 'email_parents' => 'parents-groupe@example.test',
            'date_debut_presence' => '2026-07-10', 'date_fin_presence' => '2026-07-24',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/administratif/participants');
    }

    public function testUnUtilisateurGroupePeutModifierSonParticipantMaisPasLeSupprimer(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'groupe@campement.local']);
        self::assertNotNull($utilisateur);
        $participant = static::getContainer()->get(ParticipantRepository::class)->findPourSejour($utilisateur->getGroupe()->getSejour())[0] ?? null;
        self::assertNotNull($participant);
        $client->loginUser($utilisateur);

        $crawler = $client->request('GET', '/administratif/participants/'.$participant->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer les modifications')->form(['nom' => 'Modifié par le groupe']);
        $client->submit($form);
        self::assertResponseRedirects('/administratif/participants/'.$participant->getId());
        $client->request('POST', '/administratif/participants/'.$participant->getId().'/supprimer');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/administratif/participants/pdf');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnGestionnairePeutAjouterRemplacerTelechargerEtSupprimerUneAutorisation(): void
    {
        $client = static::createClient();
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);
        $groupe = static::getContainer()->get(GroupeRepository::class)->findActifs()[0] ?? null;
        self::assertNotNull($groupe);
        $participant = (new Participant())->setGroupe($groupe)->setType(Participant::TYPE_JEUNE)
            ->setNom('Documents')->setPrenom('Jeune')->setDateNaissance(new DateTimeImmutable('2013-05-12'))
            ->setTelephoneParent1('0601020304')->setEmailParents('documents@example.test')
            ->setDateDebutPresence(new DateTimeImmutable('2026-07-10'))->setDateFinPresence(new DateTimeImmutable('2026-07-24'));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($participant);
        $entityManager->flush();

        foreach (['premiere.pdf', 'remplacement.pdf'] as $nom) {
            $crawler = $client->request('GET', '/administratif/participants/'.$participant->getId());
            $action = '/administratif/participants/'.$participant->getId().'/documents/'.DocumentParticipant::AUTORISATION_DEPART_CAMP;
            $formulaire = $crawler->filter('form[action="'.$action.'"]');
            $temporaire = tempnam(sys_get_temp_dir(), 'document-test-');
            self::assertNotFalse($temporaire);
            file_put_contents($temporaire, "%PDF-1.4\n%%EOF");
            $client->request('POST', $action, ['_token' => $formulaire->filter('input[name="_token"]')->attr('value')], [
                'documents' => [new UploadedFile($temporaire, $nom, 'application/pdf', null, true)],
            ]);
            self::assertResponseRedirects('/administratif/participants/'.$participant->getId());
        }

        $entityManager->clear();
        $participant = $entityManager->find(Participant::class, $participant->getId());
        self::assertNotNull($participant);
        self::assertCount(1, $participant->getDocuments());
        $document = $participant->getDocuments()->first();
        self::assertInstanceOf(DocumentParticipant::class, $document);
        self::assertSame('remplacement.pdf', $document->getNomFichier());

        $crawler = $client->request('GET', '/administratif/participants');
        self::assertSelectorTextContains('.document-status--ok', 'Reçu');
        $client->request('GET', '/administratif/documents/'.$document->getId().'/telecharger');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('remplacement.pdf', (string) $client->getResponse()->headers->get('content-disposition'));

        $crawler = $client->request('GET', '/administratif/participants/'.$participant->getId());
        $form = $crawler->filter('form[action="/administratif/documents/'.$document->getId().'/supprimer"]')->form();
        $client->submit($form);
        self::assertResponseRedirects('/administratif/participants/'.$participant->getId());
        $entityManager->clear();
        self::assertNull($entityManager->find(DocumentParticipant::class, $document->getId()));
    }
}
