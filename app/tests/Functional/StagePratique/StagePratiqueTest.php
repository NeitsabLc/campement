<?php

declare(strict_types=1);

namespace App\Tests\Functional\StagePratique;

use App\Entity\CommentaireStagePratique;
use App\Entity\Participant;
use App\Entity\Sejour;
use App\Repository\GroupeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ContexteSejour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StagePratiqueTest extends WebTestCase
{
    public function testUnGestionnaireSuitUnStageBafdJourParJourEtSaisitLesAvis(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $gestionnaire = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($gestionnaire);
        $client->loginUser($gestionnaire);
        $sejour = $container->get(ContexteSejour::class)->actif();
        self::assertNotNull($sejour);
        $groupe = $container->get(GroupeRepository::class)->findActifsPourSejour($sejour)[0] ?? null;
        self::assertNotNull($groupe);
        $entityManager = $container->get(EntityManagerInterface::class);
        $moduleInitial = $sejour->isModuleStagesPratiquesActif();
        $participant = (new Participant())->setGroupe($groupe)->setType(Participant::TYPE_ADULTE)
            ->setNom('Stage')->setPrenom('Morgan')->setDateNaissance(new \DateTimeImmutable('1995-05-12'))
            ->setTelephone('0611223344')->setEmail('morgan.stage@example.test')
            ->setContactUrgenceNomPrenom('Contact Stage')->setContactUrgenceTelephone('0600000000')
            ->setDateDebutPresence($groupe->getDateDebutPresence())->setDateFinPresence($groupe->getDateFinPresence());

        try {
            $sejour->setModuleStagesPratiquesActif(true);
            $entityManager->persist($participant);
            $entityManager->flush();

            $crawler = $client->request('GET', '/stages-pratiques');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Stages pratiques');
            self::assertSelectorTextContains('select[name="participant_id"]', 'Morgan Stage');
            $formAjout = $crawler->selectButton('Ajouter au suivi')->form([
                'participant_id' => (string) $participant->getId(),
                'type_stage_pratique' => 'BAFD',
            ]);
            $client->submit($formAjout);
            self::assertResponseRedirects('/stages-pratiques');

            $crawler = $client->followRedirect();
            self::assertSelectorTextContains('.stage-card', 'Morgan Stage');
            self::assertSelectorTextContains('.stage-badge', 'BAFD');
            $jour = $participant->getDateDebutPresence()->format('Y-m-d');
            $formSuivi = $crawler->selectButton('Enregistrer le suivi')->form([
                'avis_sgdf' => 'Avis du mouvement favorable.',
                'avis_formation' => 'Stage BAFD validé.',
                'commentaires['.$jour.']' => 'Bonne prise en main de la journée.',
            ]);
            $client->submit($formSuivi);
            self::assertResponseRedirects('/stages-pratiques#stage-'.$participant->getId());

            $entityManager->clear();
            $participantEnregistre = $entityManager->find(Participant::class, $participant->getId());
            self::assertInstanceOf(Participant::class, $participantEnregistre);
            self::assertSame('BAFD', $participantEnregistre->getTypeStagePratique());
            self::assertSame('Avis du mouvement favorable.', $participantEnregistre->getAvisStageSgdf());
            self::assertSame('Stage BAFD validé.', $participantEnregistre->getAvisStageFormation());
            self::assertSame('Bonne prise en main de la journée.', $entityManager->getRepository(CommentaireStagePratique::class)->findOneBy([
                'participant' => $participantEnregistre,
                'dateCommentaire' => new \DateTimeImmutable($jour),
            ])?->getCommentaire());
        } finally {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $sejour = $entityManager->find(Sejour::class, $sejour->getId());
            if (null !== $sejour) {
                $sejour->setModuleStagesPratiquesActif($moduleInitial);
            }
            $participant = $entityManager->find(Participant::class, $participant->getId());
            if (null !== $participant) {
                $entityManager->remove($participant);
            }
            $entityManager->flush();
        }
    }

    public function testLeModuleDesactiveRedirigeVersLeTableauDeBord(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $gestionnaire = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($gestionnaire);
        $client->loginUser($gestionnaire);
        $sejour = $container->get(ContexteSejour::class)->actif();
        self::assertNotNull($sejour);
        $entityManager = $container->get(EntityManagerInterface::class);
        $moduleInitial = $sejour->isModuleStagesPratiquesActif();

        try {
            $sejour->setModuleStagesPratiquesActif(false);
            $entityManager->flush();
            $client->request('GET', '/stages-pratiques');
            self::assertResponseRedirects('/');
            $client->followRedirect();
            self::assertSelectorTextContains('.flash--error', 'Ce module n’est pas actif');
            self::assertSelectorTextNotContains('.sidebar__nav', 'Stages pratiques');
        } finally {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $sejour = $entityManager->find(Sejour::class, $sejour->getId());
            if (null !== $sejour) {
                $sejour->setModuleStagesPratiquesActif($moduleInitial);
                $entityManager->flush();
            }
        }
    }
}
