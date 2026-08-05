<?php
declare(strict_types=1);
namespace App\Tests\Functional\Participant;
use App\Entity\Participant;
use App\Repository\GroupeRepository;
use App\Repository\UtilisateurRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PresenceTest extends WebTestCase
{
    public function testLeRegistreAfficheLaPresenceParDefautEtEnregistreUneAbsence():void
    {
        $client=static::createClient();$user=static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email'=>'gestionnaire@campement.local']);self::assertNotNull($user);$client->loginUser($user);
        $groupe=static::getContainer()->get(GroupeRepository::class)->findActifs()[0]??null;self::assertNotNull($groupe);
        $participant=(new Participant())->setGroupe($groupe)->setType(Participant::TYPE_JEUNE)->setNom('Présence')->setPrenom('Test')->setDateNaissance(new DateTimeImmutable('2012-01-01'))->setTelephoneParent1('0601020304')->setEmailParents('parent@example.test')->setDateDebutPresence(new DateTimeImmutable('2026-07-10'))->setDateFinPresence(new DateTimeImmutable('2026-07-12'));
        $em=static::getContainer()->get(EntityManagerInterface::class);$em->persist($participant);$em->flush();
        $client->request('GET','/administratif/registre-presence?date=2026-07-10');self::assertResponseIsSuccessful();self::assertSelectorExists('.presence-status.is-present');self::assertSelectorExists('.presence-total--young strong');self::assertSelectorExists('.presence-total--adult strong');
        $crawler=$client->request('GET',sprintf('/administratif/registre-presence/%s/2026-07-11/modifier',$participant->getId()));$form=$crawler->selectButton('Enregistrer la présence')->form(['statut'=>'absent','commentaire'=>'Sortie médicale']);$client->submit($form);self::assertResponseRedirects('/administratif/registre-presence?date=2026-07-11');$client->followRedirect();
        self::assertSelectorExists('.presence-status.is-absent');
    }

    public function testUnDepartExigeUnCommentaire():void
    {
        $client=static::createClient();$user=static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email'=>'gestionnaire@campement.local']);self::assertNotNull($user);$client->loginUser($user);
        $participant=static::getContainer()->get(EntityManagerInterface::class)->getRepository(Participant::class)->findOneBy(['nom'=>'Présence']);self::assertNotNull($participant);
        $crawler=$client->request('GET',sprintf('/administratif/registre-presence/%s/2026-07-11/modifier',$participant->getId()));$form=$crawler->selectButton('Enregistrer la présence')->form(['statut'=>'depart','commentaire'=>'']);$client->submit($form);
        self::assertResponseIsSuccessful();self::assertSelectorTextContains('.distribution-errors','commentaire est obligatoire');
    }
}
