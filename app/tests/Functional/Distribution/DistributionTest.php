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
use App\Enum\RegimeAlimentaire;
use App\Repository\TypeRepasRepository;
use App\Repository\UniteRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ArchiveListesCourses;
use App\Service\CalculStockDynamique;
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
        self::assertSelectorExists(sprintf('input[name="date_debut"][value="%s"][required]', (new \DateTimeImmutable('today'))->format('Y-m-d')));
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
                'quantites['.$fixture['denree'].'|STANDARD]' => '1',
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

    public function testLeStockEstRecalculeDepuisLesSaisiesAvecUneReferenceArchivee(): void
    {
        $client = static::createClient();
        $fixture = $this->creerFixtureDistribution('DEJEUNER', false);

        try {
            $connexion = static::getContainer()->get(Connection::class);
            $gramme = (string) $connexion->fetchOne("SELECT id FROM campement.unite WHERE nom = 'gramme'");
            $kilogramme = (string) $connexion->fetchOne("SELECT id FROM campement.unite WHERE nom = 'kilogramme'");
            $carton = (string) $connexion->fetchOne("SELECT id FROM campement.unite WHERE nom = 'carton'");
            $fournisseur = (string) $connexion->fetchOne(
                "INSERT INTO campement.fournisseur (sejour_id, nom) VALUES (:sejour, 'Fournisseur archivé') RETURNING id",
                ['sejour' => $fixture['sejour']],
            );
            $reference = (string) $connexion->fetchOne(
                "INSERT INTO campement.denree_fournisseur (fournisseur_id, denree_id, reference, actif) VALUES (:fournisseur, :denree, 'ARCHIVEE', FALSE) RETURNING id",
                ['fournisseur' => $fournisseur, 'denree' => $fixture['denree']],
            );
            $niveaux = [];
            foreach ([
                [1, $carton, '6.000', 'kilogramme'],
                [2, $kilogramme, '1000.000', 'gramme'],
                [3, $gramme, '1.000', null],
            ] as [$ordre, $conditionnement, $quantite, $libelleContenu]) {
                $niveaux[$ordre] = (string) $connexion->fetchOne(
                    'INSERT INTO campement.denree_fournisseur_conditionnement (reference_fournisseur_id, ordre, libelle, conditionnement_id, quantite_contenu, libelle_contenu, unite_contenu_id) VALUES (:reference, :ordre, :libelle, :conditionnement, :quantite, :libelle_contenu, :unite_contenu) RETURNING id',
                    [
                        'reference' => $reference,
                        'ordre' => $ordre,
                        'libelle' => 1 === $ordre ? 'carton' : (2 === $ordre ? 'kilogramme' : 'gramme'),
                        'conditionnement' => $conditionnement,
                        'quantite' => $quantite,
                        'libelle_contenu' => $libelleContenu,
                        'unite_contenu' => 3 === $ordre ? $gramme : null,
                    ],
                );
            }
            $connexion->executeStatement(
                'UPDATE campement.denree SET unite_reference_id = :gramme, unite_inventaire_id = :kilogramme WHERE id = :denree',
                ['gramme' => $gramme, 'kilogramme' => $kilogramme, 'denree' => $fixture['denree']],
            );
            $connexion->executeStatement(
                'UPDATE campement.menu_denree SET conditionnement_id = :gramme WHERE menu_id = :menu AND denree_id = :denree',
                ['gramme' => $gramme, 'menu' => $fixture['menu'], 'denree' => $fixture['denree']],
            );
            $mouvementEntree = (string) $connexion->fetchOne(
                "INSERT INTO campement.mouvement_stock (sejour_id, utilisateur_id, type_mouvement_id, origine_mouvement_id, date_mouvement) SELECT :sejour, utilisateur.id, type.id, origine.id, NOW() FROM campement.utilisateur utilisateur CROSS JOIN campement.type_mouvement type CROSS JOIN campement.origine_mouvement origine WHERE utilisateur.email = 'saisie-consommation@campement.local' AND type.code = 'ENTREE' AND origine.code = 'FOURNISSEUR' RETURNING id",
                ['sejour' => $fixture['sejour']],
            );
            $ligneEntree = (string) $connexion->fetchOne(
                'INSERT INTO campement.mouvement_stock_ligne (mouvement_stock_id, denree_id, reference_fournisseur_id) VALUES (:mouvement, :denree, :reference) RETURNING id',
                ['mouvement' => $mouvementEntree, 'denree' => $fixture['denree'], 'reference' => $reference],
            );
            $connexion->executeStatement(
                'INSERT INTO campement.mouvement_stock_ligne_conditionnement (mouvement_stock_ligne_id, conditionnement_id, quantite) VALUES (:ligne, :conditionnement, 1)',
                ['ligne' => $ligneEntree, 'conditionnement' => $niveaux[1]],
            );
            static::getContainer()->get(EntityManagerInterface::class)->clear();

            $crawler = $client->request('GET', '/distribution/'.$fixture['jeton']);
            self::assertResponseIsSuccessful();
            $client->submit($crawler->selectButton('Vérifier la distribution')->form([
                'groupe' => $fixture['groupe'],
                'menu' => $fixture['menu'],
                'quantites['.$fixture['denree'].'|STANDARD]' => '1060',
            ]));
            self::assertResponseIsSuccessful();
            $client->submit($client->getCrawler()->selectButton('Confirmer la distribution')->form());
            self::assertResponseIsSuccessful();

            $sortie = $connexion->fetchAssociative(
                'SELECT ligne.quantite_saisie, unite.nom AS conditionnement FROM campement.mouvement_stock_ligne ligne JOIN campement.mouvement_stock mouvement ON mouvement.id = ligne.mouvement_stock_id JOIN campement.unite unite ON unite.id = ligne.conditionnement_saisie_id WHERE mouvement.menu_id = :menu AND ligne.denree_id = :denree',
                ['menu' => $fixture['menu'], 'denree' => $fixture['denree']],
            );
            self::assertIsArray($sortie);
            self::assertSame(1060.0, (float) $sortie['quantite_saisie']);
            self::assertSame('gramme', $sortie['conditionnement']);
            static::getContainer()->get(EntityManagerInterface::class)->clear();
            $sejour = static::getContainer()->get(EntityManagerInterface::class)->find(Sejour::class, $fixture['sejour']);
            $denree = static::getContainer()->get(EntityManagerInterface::class)->find(Denree::class, $fixture['denree']);
            self::assertInstanceOf(Sejour::class, $sejour);
            self::assertInstanceOf(Denree::class, $denree);
            $stock = static::getContainer()->get(CalculStockDynamique::class)->pourDenrees($sejour, [$denree])[$fixture['denree']];
            self::assertSame(6.0, $stock['entrees']);
            self::assertSame(1.06, $stock['sorties']);
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

    public function testLesRegimesDuGroupeFiltrentLaDistributionEtLesSortiesSontAgregees(): void
    {
        $client = static::createClient();
        $fixture = $this->creerFixtureDistribution('DEJEUNER', false);

        try {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $groupe = $entityManager->find(Groupe::class, $fixture['groupe']);
            $menu = $entityManager->find(Menu::class, $fixture['menu']);
            $denree = $entityManager->find(Denree::class, $fixture['denree']);
            self::assertInstanceOf(Groupe::class, $groupe);
            self::assertInstanceOf(Menu::class, $menu);
            self::assertInstanceOf(Denree::class, $denree);
            $groupe->setNombreVegetariens(3);
            $menu->addDenree((new MenuDenree())
                ->setDenree($denree)
                ->setConditionnement($denree->getUniteReference())
                ->setRegime(RegimeAlimentaire::VEGETARIEN));
            $entityManager->flush();

            $crawler = $client->request('GET', '/distribution/'.$fixture['jeton']);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-regime="VEGETARIEN"]');
            self::assertSelectorTextContains('[data-regime="VEGETARIEN"]', 'Végétarien');
            self::assertSelectorExists(sprintf('option[value="%s"][data-regime-vegetarien="3"]', $fixture['groupe']));

            $formulaire = $crawler->selectButton('Vérifier la distribution')->form([
                'groupe' => $fixture['groupe'],
                'menu' => $fixture['menu'],
                'quantites['.$fixture['denree'].'|STANDARD]' => '1',
                'quantites['.$fixture['denree'].'|VEGETARIEN]' => '2',
            ]);
            $client->submit($formulaire);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.distribution-summary', 'Végétarien');

            $client->submit($client->getCrawler()->selectButton('Confirmer la distribution')->form());
            self::assertResponseIsSuccessful();
            $sortie = static::getContainer()->get(Connection::class)->fetchAssociative(
                'SELECT COUNT(*) AS nombre, MAX(quantite_saisie) AS quantite FROM campement.mouvement_stock_ligne ligne JOIN campement.mouvement_stock mouvement ON mouvement.id = ligne.mouvement_stock_id WHERE mouvement.menu_id = :menu AND ligne.denree_id = :denree',
                ['menu' => $fixture['menu'], 'denree' => $fixture['denree']],
            );
            self::assertIsArray($sortie);
            self::assertSame(1, (int) $sortie['nombre']);
            self::assertSame(3.0, (float) $sortie['quantite']);
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
        $connexion->executeStatement(
            'DELETE FROM campement.denree_fournisseur_conditionnement WHERE reference_fournisseur_id IN (SELECT reference.id FROM campement.denree_fournisseur reference JOIN campement.fournisseur fournisseur ON fournisseur.id = reference.fournisseur_id WHERE fournisseur.sejour_id = :sejour)',
            $parametres,
        );
        $connexion->executeStatement(
            'DELETE FROM campement.denree_fournisseur WHERE fournisseur_id IN (SELECT id FROM campement.fournisseur WHERE sejour_id = :sejour)',
            $parametres,
        );
        $connexion->executeStatement('DELETE FROM campement.fournisseur WHERE sejour_id = :sejour', $parametres);
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
