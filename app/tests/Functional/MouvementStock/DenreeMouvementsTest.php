<?php

declare(strict_types=1);

namespace App\Tests\Functional\MouvementStock;

use App\Entity\Denree;
use App\Entity\Fournisseur;
use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Entity\MouvementStockLigneConditionnement;
use App\Entity\OrigineMouvement;
use App\Entity\ReferenceFournisseur;
use App\Entity\ReferenceFournisseurConditionnement;
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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DenreeMouvementsTest extends WebTestCase
{
    public function testLaDenreeDonneAccesAuxMouvementsEtConserveLaSaisieMultiConditionnements(): void
    {
        $client = static::createClient();
        $fixture = $this->creerMouvementConditionne();
        $client->loginUser($fixture['utilisateur']);

        try {
            $crawler = $client->request('GET', '/denrees');
            self::assertResponseIsSuccessful();
            $ligneDenree = $crawler->filter(sprintf('[data-name="%s"]', mb_strtolower($fixture['nom'])));
            self::assertCount(1, $ligneDenree);
            self::assertSame(
                '/denrees/'.$fixture['denree'].'/mouvements',
                $ligneDenree->filter('.food-actions > a')->first()->attr('href'),
            );
            self::assertSelectorExists(sprintf(
                '[data-name="%s"] a[data-swipe-actions-target="startAction"]',
                mb_strtolower($fixture['nom']),
            ));

            $client->request('GET', '/denrees/'.$fixture['denree'].'/mouvements');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Mouvements de '.$fixture['nom']);
            self::assertSelectorTextContains('.food-movements-row:not(.food-movements-row--head)', 'Entrée');
            self::assertSelectorTextContains('.food-movements-row:not(.food-movements-row--head)', 'Inventaire');
            self::assertSelectorTextContains('.food-movements-row:not(.food-movements-row--head)', '3 cartons - 4 conserves');
        } finally {
            $this->supprimerFixture($fixture);
        }
    }

    /** @return array{denree: string, fournisseur: string, mouvement: string, nom: string, utilisateur: Utilisateur} */
    private function creerMouvementConditionne(): array
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $utilisateur = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        $type = $container->get(TypeMouvementRepository::class)->findOneBy(['code' => 'ENTREE', 'actif' => true]);
        $origine = $container->get(OrigineMouvementRepository::class)->findOneBy(['code' => 'INVENTAIRE', 'actif' => true]);
        $carton = $container->get(UniteRepository::class)->findOneBy(['nom' => 'carton']);
        $conserve = $container->get(UniteRepository::class)->findOneBy(['nom' => 'conserve']);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);
        self::assertInstanceOf(TypeMouvement::class, $type);
        self::assertInstanceOf(OrigineMouvement::class, $origine);
        self::assertInstanceOf(Unite::class, $carton);
        self::assertInstanceOf(Unite::class, $conserve);
        $sejour = $utilisateur->getSejoursGeres()->filter(
            static fn (Sejour $sejour): bool => $sejour->isActif(),
        )->first();
        self::assertInstanceOf(Sejour::class, $sejour);
        $utilisateur->setDernierSejour($sejour);

        $suffixe = bin2hex(random_bytes(5));
        $nom = 'Historique '.$suffixe;
        $denree = (new Denree($sejour))
            ->setNom($nom)
            ->setUniteReference($conserve)
            ->setUniteInventaire($carton);
        $fournisseur = new Fournisseur($sejour, 'Fournisseur '.$suffixe);
        $reference = new ReferenceFournisseur($fournisseur, $denree, 'REF-'.$suffixe);
        $niveauCarton = new ReferenceFournisseurConditionnement($reference, 1, 'carton', '12.000', null, 'conserve', $carton);
        $niveauConserve = new ReferenceFournisseurConditionnement($reference, 2, 'conserve', '1.000', $conserve, null, $conserve);
        $mouvement = new MouvementStock($sejour, $utilisateur, $type, $origine);
        $ligne = (new MouvementStockLigne($mouvement, $denree, null))
            ->setReferenceFournisseur($reference);
        $detailCarton = new MouvementStockLigneConditionnement($ligne, $niveauCarton, '3.000');
        $detailConserve = new MouvementStockLigneConditionnement($ligne, $niveauConserve, '4.000');
        foreach ([$denree, $fournisseur, $reference, $niveauCarton, $niveauConserve, $mouvement, $ligne, $detailCarton, $detailConserve] as $entite) {
            $em->persist($entite);
        }
        $em->flush();

        return [
            'denree' => (string) $denree->getId(),
            'fournisseur' => (string) $fournisseur->getId(),
            'mouvement' => (string) $mouvement->getId(),
            'nom' => $nom,
            'utilisateur' => $utilisateur,
        ];
    }

    /** @param array{denree: string, fournisseur: string, mouvement: string} $fixture */
    private function supprimerFixture(array $fixture): void
    {
        $connexion = static::getContainer()->get(Connection::class);
        $connexion->executeStatement('DELETE FROM campement.mouvement_stock WHERE id = :id', ['id' => $fixture['mouvement']]);
        $connexion->executeStatement('DELETE FROM campement.denree_fournisseur WHERE denree_id = :id', ['id' => $fixture['denree']]);
        $connexion->executeStatement('DELETE FROM campement.fournisseur WHERE id = :id', ['id' => $fixture['fournisseur']]);
        $connexion->executeStatement('DELETE FROM campement.denree WHERE id = :id', ['id' => $fixture['denree']]);
    }
}
