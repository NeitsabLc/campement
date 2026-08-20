<?php

declare(strict_types=1);

namespace App\Tests\Functional\Distribution;

use App\Entity\Denree;
use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use App\Entity\TypeRepas;
use App\Entity\Unite;
use App\Entity\Utilisateur;
use App\Repository\TypeRepasRepository;
use App\Repository\UniteRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ArchiveListesCourses;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DistributionTest extends WebTestCase
{
    public function testLeQrCodeChangeImmediatementApresLeRenouvellementDuLien(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $crawler = $client->request('GET', '/intendance/distribution');
        self::assertResponseIsSuccessful();
        $ancienneUrlQrCode = $crawler->filter('.distribution-qr img')->attr('src');

        $client->submit($crawler->selectButton('Générer un nouveau lien')->form());
        self::assertResponseRedirects('/intendance/distribution');
        $crawler = $client->followRedirect();

        self::assertNotSame($ancienneUrlQrCode, $crawler->filter('.distribution-qr img')->attr('src'));
    }

    public function testLeQrCodeNePeutPasEtreConserveDansLeCache(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $client->request('GET', '/intendance/distribution/qr-code');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testLeGestionnairePeutTelechargerUneArchiveDeListesDeCourses(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $sejour = $gestionnaire->getDernierSejour();
        self::assertInstanceOf(Sejour::class, $sejour);
        $client->request('GET', sprintf(
            '/intendance/distribution/listes-courses?date_debut=%s&date_fin=%s',
            $sejour->getDateDebut()->format('Y-m-d'),
            $sejour->getDateFin()->format('Y-m-d'),
        ));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/zip');
        self::assertStringContainsString('attachment;', (string) $client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testLeBoutonDesListesDeCoursesEstAfficheSurLaPageDistribution(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $crawler = $client->request('GET', '/intendance/distribution');

        self::assertResponseIsSuccessful();
        $sejour = $gestionnaire->getDernierSejour();
        self::assertInstanceOf(Sejour::class, $sejour);
        self::assertSelectorExists('button[data-open-dialog="shopping-lists-period"]');
        self::assertSelectorExists('dialog#shopping-lists-period[aria-labelledby="shopping-lists-period-title"]');
        self::assertSelectorExists('[data-distribution-admin-target="downloadStatus"][aria-live="polite"]');
        self::assertSelectorExists('form[data-action="submit->distribution-admin#download"]');
        self::assertSelectorExists('button[type="submit"][data-distribution-admin-target="downloadButton"]');
        self::assertSelectorExists(sprintf('input[name="date_debut"][value="%s"][required]', $sejour->getDateDebut()->format('Y-m-d')));
        self::assertSelectorExists(sprintf('input[name="date_fin"][value="%s"][required]', $sejour->getDateFin()->format('Y-m-d')));
    }

    public function testLaPeriodeDesListesDeCoursesEstValideeCoteServeur(): void
    {
        $client = static::createClient();
        $gestionnaire = static::getContainer()->get(UtilisateurRepository::class)->findOneBy([
            'email' => 'gestionnaire@campement.local',
        ]);
        self::assertInstanceOf(Utilisateur::class, $gestionnaire);
        $client->loginUser($gestionnaire);

        $client->request('GET', '/intendance/distribution/listes-courses?date_debut=2026-07-04&date_fin=2026-07-03');

        self::assertResponseRedirects('/intendance/distribution');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Sélectionnez une période valide');
    }

    public function testLArchiveNeContientQueLesRepasEtUnitesPresentsSurLaPeriode(): void
    {
        $fixture = $this->creerFixtureDistribution('DEJEUNER', false);
        $archive = null;
        $archiveVide = null;

        try {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $sejour = $entityManager->find(Sejour::class, $fixture['sejour']);
            self::assertInstanceOf(Sejour::class, $sejour);
            $aujourdhui = new \DateTimeImmutable('today');
            $groupeAbsent = (new Groupe())
                ->setSejour($sejour)
                ->setNom('Groupe absent')
                ->setType('scouts-guides')
                ->setDateDebutPresence($aujourdhui->modify('+1 day'))
                ->setDateFinPresence($aujourdhui->modify('+1 day'));
            $entityManager->persist($groupeAbsent);
            $entityManager->flush();

            $generateur = static::getContainer()->get(ArchiveListesCourses::class);
            $archive = $generateur->generer($sejour, $aujourdhui, $aujourdhui);
            $fichiers = $this->fichiersArchive($archive);
            self::assertTrue(array_any($fichiers, static fn (string $fichier): bool => str_contains($fichier, '/groupe-de-test_')));
            self::assertFalse(array_any($fichiers, static fn (string $fichier): bool => str_contains($fichier, '/groupe-absent_')));

            $demain = $aujourdhui->modify('+1 day');
            $archiveVide = $generateur->generer($sejour, $demain, $demain);
            self::assertContains('AUCUNE_LISTE.txt', $this->fichiersArchive($archiveVide));
        } finally {
            if (is_string($archive)) {
                @unlink($archive);
            }
            if (is_string($archiveVide)) {
                @unlink($archiveVide);
            }
            $this->supprimerFixtureDistribution($fixture['sejour']);
        }
    }

    public function testConsulterLeLienPublicNeCreePlusDeMenu(): void
    {
        $client = static::createClient();
        $fixture = $this->creerFixtureDistribution('GOUTER', true);

        try {
            $connexion = static::getContainer()->get(Connection::class);
            $avant = (int) $connexion->fetchOne(
                'SELECT COUNT(*) FROM campement.menu WHERE sejour_id = :sejour',
                ['sejour' => $fixture['sejour']],
            );

            $client->request('GET', '/distribution/'.$fixture['jeton']);

            self::assertResponseIsSuccessful();
            self::assertSame($avant, (int) static::getContainer()->get(Connection::class)->fetchOne(
                'SELECT COUNT(*) FROM campement.menu WHERE sejour_id = :sejour',
                ['sejour' => $fixture['sejour']],
            ));
        } finally {
            $this->supprimerFixtureDistribution($fixture['sejour']);
        }
    }

    public function testUneConfirmationRejoueeNeCreeQuUnMouvement(): void
    {
        $client = static::createClient();
        $fixture = $this->creerFixtureDistribution('DEJEUNER', false);

        try {
            $crawler = $client->request('GET', '/distribution/'.$fixture['jeton']);
            self::assertResponseIsSuccessful();
            $formulaire = $crawler->selectButton('Vérifier la distribution')->form([
                'groupe' => $fixture['groupe'],
                'menu' => $fixture['menu'],
                'quantites['.$fixture['denree'].']' => '1',
            ]);
            $client->submit($formulaire);
            self::assertResponseIsSuccessful();

            $confirmation = $client->getCrawler()->selectButton('Confirmer la distribution')->form([
                'date_navigateur' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'heure_navigateur' => (new \DateTimeImmutable())->format('H:i:s'),
                'decalage_utc' => '0',
            ]);
            $parametres = $confirmation->getPhpValues();
            $cleSoumission = (string) $parametres['cle_soumission'];
            self::assertNotSame('', $cleSoumission);

            $client->submit($confirmation);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Distribution enregistrée');

            $client->request('POST', '/distribution/'.$fixture['jeton'], $parametres);
            self::assertResponseIsSuccessful();
            self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne(
                'SELECT COUNT(*) FROM campement.mouvement_stock WHERE cle_soumission = :cle',
                ['cle' => $cleSoumission],
            ));
        } finally {
            $this->supprimerFixtureDistribution($fixture['sejour']);
        }
    }

    public function testLaPageExpliqueQuandAucunMenuNEstConfigure(): void
    {
        $client = static::createClient();
        $fixture = $this->creerFixtureDistribution('DEJEUNER', false);

        try {
            $connexion = static::getContainer()->get(Connection::class);
            $connexion->executeStatement('DELETE FROM campement.menu_denree WHERE menu_id = :menu', ['menu' => $fixture['menu']]);
            $connexion->executeStatement('DELETE FROM campement.menu WHERE id = :menu', ['menu' => $fixture['menu']]);

            $client->request('GET', '/distribution/'.$fixture['jeton']);

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.distribution-empty', 'Aucun menu n’est configuré.');
            self::assertSelectorTextNotContains('.distribution-empty', 'Aucune unité n’est présente aujourd’hui.');
        } finally {
            $this->supprimerFixtureDistribution($fixture['sejour']);
        }
    }

    public function testLaPageExpliqueQuandAucuneUniteNEstPresente(): void
    {
        $client = static::createClient();
        $fixture = $this->creerFixtureDistribution('DEJEUNER', false);

        try {
            static::getContainer()->get(Connection::class)->executeStatement(
                'UPDATE campement.groupe SET actif = FALSE WHERE id = :groupe',
                ['groupe' => $fixture['groupe']],
            );
            static::getContainer()->get(EntityManagerInterface::class)->clear();

            $client->request('GET', '/distribution/'.$fixture['jeton']);

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.distribution-empty', 'Aucune unité n’est présente aujourd’hui.');
            self::assertSelectorTextNotContains('.distribution-empty', 'Aucun menu n’est configuré.');
        } finally {
            $this->supprimerFixtureDistribution($fixture['sejour']);
        }
    }

    public function testLeLienPublicFermeApresLeDernierJourDuSejour(): void
    {
        $client = static::createClient();
        $fixture = $this->creerFixtureDistribution('DEJEUNER', false);

        try {
            $connexion = static::getContainer()->get(Connection::class);
            $aujourdhui = new \DateTimeImmutable('today');
            $connexion->executeStatement(
                'UPDATE campement.sejour SET date_fin = :date_fin WHERE id = :sejour',
                ['date_fin' => $aujourdhui->format('Y-m-d'), 'sejour' => $fixture['sejour']],
            );
            static::getContainer()->get(EntityManagerInterface::class)->clear();

            $client->request('GET', '/distribution/'.$fixture['jeton']);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.distribution-action .distribution-button');

            $connexion->executeStatement(
                'UPDATE campement.sejour SET date_fin = :date_fin WHERE id = :sejour',
                ['date_fin' => $aujourdhui->modify('-1 day')->format('Y-m-d'), 'sejour' => $fixture['sejour']],
            );
            static::getContainer()->get(EntityManagerInterface::class)->clear();

            $client->request('GET', '/distribution/'.$fixture['jeton']);

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.distribution-empty', 'Distribution indisponible');
            self::assertSelectorNotExists('.distribution-action .distribution-button');
        } finally {
            $this->supprimerFixtureDistribution($fixture['sejour']);
        }
    }

    /** @return array{sejour: string, jeton: string, groupe: string, menu: string, denree: string} */
    private function creerFixtureDistribution(string $codeRepas, bool $fusionGouterDejeuner): array
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $typeRepas = $container->get(TypeRepasRepository::class)->findOneBy(['code' => $codeRepas, 'actif' => true]);
        $unite = $container->get(UniteRepository::class)->findOneBy(['actif' => true]);
        self::assertInstanceOf(TypeRepas::class, $typeRepas);
        self::assertInstanceOf(Unite::class, $unite);

        $aujourdhui = new \DateTimeImmutable('today');
        $sejour = (new Sejour(
            'Distribution '.bin2hex(random_bytes(4)),
            $aujourdhui->modify('-1 day'),
            $aujourdhui->modify('+1 day'),
        ))->setDistribuerGouterDejeuner($fusionGouterDejeuner);
        $repas = new SejourTypeRepas($sejour, $typeRepas);
        $groupe = (new Groupe())
            ->setSejour($sejour)
            ->setNom('Groupe de test')
            ->setType('FARFADETS')
            ->setDateDebutPresence($sejour->getDateDebut())
            ->setDateFinPresence($sejour->getDateFin());
        $denree = (new Denree($sejour))
            ->setNom('Denrée '.bin2hex(random_bytes(4)))
            ->setUniteReference($unite)
            ->setUniteInventaire($unite);
        $ligne = (new MenuDenree())
            ->setDenree($denree)
            ->setConditionnement($unite);
        $menu = (new Menu())
            ->setSejour($sejour)
            ->setSejourTypeRepas($repas)
            ->setDateMenu($aujourdhui)
            ->addDenree($ligne);

        foreach ([$sejour, $repas, $groupe, $denree, $menu] as $entite) {
            $entityManager->persist($entite);
        }
        $entityManager->flush();

        return [
            'sejour' => (string) $sejour->getId(),
            'jeton' => $sejour->getJetonDistributionPublique()->toRfc4122(),
            'groupe' => (string) $groupe->getId(),
            'menu' => (string) $menu->getId(),
            'denree' => (string) $denree->getId(),
        ];
    }

    private function supprimerFixtureDistribution(string $sejourId): void
    {
        $connexion = static::getContainer()->get(Connection::class);
        $parametres = ['sejour' => $sejourId];
        $connexion->executeStatement(
            'DELETE FROM campement.mouvement_stock WHERE sejour_id = :sejour',
            $parametres,
        );
        $connexion->executeStatement(
            'DELETE FROM campement.menu_denree WHERE menu_id IN (SELECT id FROM campement.menu WHERE sejour_id = :sejour)',
            $parametres,
        );
        $connexion->executeStatement('DELETE FROM campement.menu WHERE sejour_id = :sejour', $parametres);
        $connexion->executeStatement('DELETE FROM campement.denree WHERE sejour_id = :sejour', $parametres);
        $connexion->executeStatement('DELETE FROM campement.sejour WHERE id = :sejour', $parametres);
    }

    /** @return list<string> */
    private function fichiersArchive(string $chemin): array
    {
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($chemin));
        $fichiers = [];
        for ($index = 0; $index < $archive->numFiles; ++$index) {
            $nom = $archive->getNameIndex($index);
            if (false !== $nom) {
                $fichiers[] = $nom;
            }
        }
        $archive->close();

        return $fichiers;
    }
}
