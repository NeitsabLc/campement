<?php

declare(strict_types=1);

namespace App\Tests\Functional\Intendance;

use App\Entity\Denree;
use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Entity\OrigineMouvement;
use App\Entity\Recette;
use App\Entity\Sejour;
use App\Entity\TypeMouvement;
use App\Entity\Unite;
use App\Entity\Utilisateur;
use App\Repository\OrigineMouvementRepository;
use App\Repository\TypeMouvementRepository;
use App\Repository\UniteRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class TriCataloguesTest extends WebTestCase
{
    public function testLesRecettesSontTrieesParNomEtParCategorieDansLesDeuxSens(): void
    {
        [$client, $utilisateur, $sejour, $sejourInitial] = $this->connecterGestionnaire();
        $prefixe = 'Tri recettes '.bin2hex(random_bytes(4)).' ';
        $recettes = [
            (new Recette($sejour))->setNom($prefixe.'Alpha')->setCategorie('DESSERT'),
            (new Recette($sejour))->setNom($prefixe.'Beta')->setCategorie('PLAT'),
            (new Recette($sejour))->setNom($prefixe.'Zeta')->setCategorie('ENTREE'),
        ];
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($recettes as $recette) {
            $em->persist($recette);
        }
        $em->flush();

        try {
            $crawler = $client->request('GET', '/recettes?tri=nom&ordre=desc');
            self::assertResponseIsSuccessful();
            self::assertSame(
                [$prefixe.'Zeta', $prefixe.'Beta', $prefixe.'Alpha'],
                $this->nomsAffiches($crawler, $prefixe),
            );
            self::assertSelectorExists('[role="columnheader"][aria-sort="descending"] .foods-sort-link');
            self::assertSelectorExists('[role="columnheader"][aria-sort="descending"] [data-sort-state="descending"]');
            self::assertSelectorExists('[role="columnheader"][aria-sort="none"] [data-sort-state="none"]');

            $crawler = $client->request('GET', '/recettes?tri=categorie&ordre=asc');
            self::assertSame(
                [$prefixe.'Alpha', $prefixe.'Zeta', $prefixe.'Beta'],
                $this->nomsAffiches($crawler, $prefixe),
            );

            $crawler = $client->request('GET', '/recettes?tri=categorie&ordre=desc');
            self::assertSame(
                [$prefixe.'Beta', $prefixe.'Zeta', $prefixe.'Alpha'],
                $this->nomsAffiches($crawler, $prefixe),
            );
        } finally {
            $this->supprimerEntites($recettes);
            $this->supprimerSejour($utilisateur, $sejour, $sejourInitial);
        }
    }

    public function testLesDenreesSontTrieesParLibelleEtParStockDansLesDeuxSens(): void
    {
        [$client, $utilisateur, $sejour, $sejourInitial] = $this->connecterGestionnaire();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $typeEntree = $container->get(TypeMouvementRepository::class)->findOneBy(['code' => 'ENTREE', 'actif' => true]);
        $typeSortie = $container->get(TypeMouvementRepository::class)->findOneBy(['code' => 'SORTIE', 'actif' => true]);
        $origine = $container->get(OrigineMouvementRepository::class)->findOneBy(['code' => 'CORRECTION', 'actif' => true]);
        $uniteGramme = $container->get(UniteRepository::class)->findOneBy(['nom' => 'gramme', 'actif' => true]);
        $uniteCarton = $container->get(UniteRepository::class)->findOneBy(['nom' => 'carton', 'actif' => true]);
        self::assertInstanceOf(TypeMouvement::class, $typeEntree);
        self::assertInstanceOf(TypeMouvement::class, $typeSortie);
        self::assertInstanceOf(OrigineMouvement::class, $origine);
        self::assertInstanceOf(Unite::class, $uniteGramme);
        self::assertInstanceOf(Unite::class, $uniteCarton);

        $prefixe = 'Tri denrées '.bin2hex(random_bytes(4)).' ';
        $denrees = [
            (new Denree($sejour))->setNom($prefixe.'Alpha')->setUniteReference($uniteGramme)->setUniteInventaire($uniteGramme),
            (new Denree($sejour))->setNom($prefixe.'Beta')->setUniteReference($uniteCarton)->setUniteInventaire($uniteCarton),
            (new Denree($sejour))->setNom($prefixe.'Zeta')->setUniteReference($uniteGramme)->setUniteInventaire($uniteGramme),
        ];
        $entree = new MouvementStock($sejour, $utilisateur, $typeEntree, $origine);
        $sortie = new MouvementStock($sejour, $utilisateur, $typeSortie, $origine);
        foreach ($denrees as $denree) {
            $em->persist($denree);
        }
        // Les unités diffèrent volontairement : seul le nombre affiché pilote le tri.
        $em->persist((new MouvementStockLigne($entree, $denrees[0], '1'))->setConditionnementSaisie($uniteGramme));
        $em->persist((new MouvementStockLigne($sortie, $denrees[1], '2'))->setConditionnementSaisie($uniteCarton));
        $em->persist((new MouvementStockLigne($entree, $denrees[2], '20'))->setConditionnementSaisie($uniteGramme));
        $em->persist($entree);
        $em->persist($sortie);
        $em->flush();

        try {
            $crawler = $client->request('GET', '/denrees?tri=nom&ordre=desc');
            self::assertResponseIsSuccessful();
            self::assertSame(
                [$prefixe.'Zeta', $prefixe.'Beta', $prefixe.'Alpha'],
                $this->nomsAffiches($crawler, $prefixe),
            );

            $crawler = $client->request('GET', '/denrees?tri=stock&ordre=asc');
            self::assertSame(
                [$prefixe.'Beta', $prefixe.'Alpha', $prefixe.'Zeta'],
                $this->nomsAffiches($crawler, $prefixe),
            );
            self::assertSelectorExists('.foods-row--head .foods-sort-link[aria-label^="Trier les stocks"]');
            self::assertSelectorExists('.foods-row--head [data-sort-state="ascending"]');

            $crawler = $client->request('GET', '/denrees?tri=stock&ordre=desc');
            self::assertSame(
                [$prefixe.'Zeta', $prefixe.'Alpha', $prefixe.'Beta'],
                $this->nomsAffiches($crawler, $prefixe),
            );
        } finally {
            $connexion = $container->get(Connection::class);
            foreach ([$entree, $sortie] as $mouvement) {
                $connexion->executeStatement('DELETE FROM campement.mouvement_stock WHERE id = :id', ['id' => (string) $mouvement->getId()]);
            }
            $this->supprimerEntites($denrees);
            $this->supprimerSejour($utilisateur, $sejour, $sejourInitial);
        }
    }

    /** @return array{KernelBrowser, Utilisateur, Sejour, Sejour|null} */
    private function connecterGestionnaire(): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)
            ->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);
        $sejourInitial = $utilisateur->getDernierSejour();
        $sejour = new Sejour(
            'Séjour test des tris '.bin2hex(random_bytes(4)),
            new \DateTimeImmutable('2028-07-01'),
            new \DateTimeImmutable('2028-07-15'),
        );
        $utilisateur->addSejourGere($sejour)->setDernierSejour($sejour);
        $em->persist($sejour);
        $em->flush();
        $client->loginUser($utilisateur);

        return [$client, $utilisateur, $sejour, $sejourInitial];
    }

    /** @return list<string> */
    private function nomsAffiches(Crawler $crawler, string $prefixe): array
    {
        return array_values(array_filter(
            $crawler->filter('[data-food-catalog-target="row"] strong')->each(
                static fn (Crawler $noeud): string => trim($noeud->text()),
            ),
            static fn (string $nom): bool => str_starts_with($nom, $prefixe),
        ));
    }

    /** @param list<Recette|Denree> $entites */
    private function supprimerEntites(array $entites): void
    {
        $connexion = static::getContainer()->get(Connection::class);
        foreach ($entites as $entite) {
            $table = $entite instanceof Recette ? 'recette' : 'denree';
            $connexion->executeStatement(
                sprintf('DELETE FROM campement.%s WHERE id = :id', $table),
                ['id' => (string) $entite->getId()],
            );
        }
    }

    private function supprimerSejour(Utilisateur $utilisateur, Sejour $sejour, ?Sejour $sejourInitial): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $utilisateur->setDernierSejour($sejourInitial)->removeSejourGere($sejour);
        $em->flush();
        static::getContainer()->get(Connection::class)->executeStatement(
            'DELETE FROM campement.sejour WHERE id = :id',
            ['id' => (string) $sejour->getId()],
        );
    }
}
