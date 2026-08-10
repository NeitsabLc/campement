<?php

declare(strict_types=1);

namespace App\Tests\Functional\MouvementStock;

use App\Entity\Denree;
use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Entity\OrigineMouvement;
use App\Entity\Sejour;
use App\Entity\TypeMouvement;
use App\Entity\Unite;
use App\Entity\Utilisateur;
use App\Repository\DenreeRepository;
use App\Repository\OrigineMouvementRepository;
use App\Repository\TypeMouvementRepository;
use App\Repository\UniteRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MouvementStockAuditTest extends WebTestCase
{
    public function testUneModificationExigeUnMotifEtConserveLesDeuxEtats(): void
    {
        $client = static::createClient();
        $fixture = $this->creerMouvement();
        $client->loginUser($fixture['utilisateur']);

        try {
            $crawler = $client->request('GET', '/stocks/mouvement/'.$fixture['mouvement']);
            self::assertResponseIsSuccessful();
            $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
            $parametres = $this->parametresModification($fixture, $token, '');

            $client->request('POST', '/stocks/mouvement/'.$fixture['mouvement'], $parametres);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('[role="alert"]', 'motif de modification est obligatoire');

            $parametres['motif_audit'] = 'Correction de la quantité saisie.';
            $client->request('POST', '/stocks/mouvement/'.$fixture['mouvement'], $parametres);
            self::assertResponseRedirects('/stocks');

            $audit = static::getContainer()->get(Connection::class)->fetchAssociative(
                'SELECT action, motif, etat_avant, etat_apres FROM campement.audit_mouvement_stock WHERE mouvement_stock_id = :id',
                ['id' => $fixture['mouvement']],
            );
            self::assertIsArray($audit);
            self::assertSame('MODIFICATION', $audit['action']);
            self::assertSame('Correction de la quantité saisie.', $audit['motif']);
            self::assertSame('1.000', json_decode((string) $audit['etat_avant'], true, flags: JSON_THROW_ON_ERROR)['lignes'][0]['quantite_unite_reference']);
            self::assertSame('2.000', json_decode((string) $audit['etat_apres'], true, flags: JSON_THROW_ON_ERROR)['lignes'][0]['quantite_unite_reference']);
        } finally {
            $this->supprimerFixture($fixture);
        }
    }

    public function testUneAnnulationEstAuditeeEtRetireLeMouvementDuStock(): void
    {
        $client = static::createClient();
        $fixture = $this->creerMouvement();
        $client->loginUser($fixture['utilisateur']);

        try {
            $crawler = $client->request('GET', '/stocks/mouvement/'.$fixture['mouvement'].'/annuler');
            self::assertResponseIsSuccessful();
            $client->submit($crawler->selectButton('Confirmer l’annulation')->form([
                'motif' => 'Livraison enregistrée deux fois.',
            ]));
            self::assertResponseRedirects('/stocks');

            $connexion = static::getContainer()->get(Connection::class);
            self::assertNotFalse($connexion->fetchOne(
                'SELECT annule_at FROM campement.mouvement_stock WHERE id = :id',
                ['id' => $fixture['mouvement']],
            ));
            self::assertSame('ANNULATION', $connexion->fetchOne(
                'SELECT action FROM campement.audit_mouvement_stock WHERE mouvement_stock_id = :id',
                ['id' => $fixture['mouvement']],
            ));

            $stocks = static::getContainer()->get(DenreeRepository::class)->findPourGestion($fixture['sejour'], true);
            $stock = array_values(array_filter($stocks, fn (array $ligne): bool => (string) $ligne['denree']->getId() === $fixture['denree']))[0] ?? null;
            self::assertIsArray($stock);
            self::assertSame(0.0, (float) $stock['stockEntree']);
        } finally {
            $this->supprimerFixture($fixture);
        }
    }

    public function testUneSuppressionConserveUneTraceIndependanteDuMouvement(): void
    {
        $client = static::createClient();
        $fixture = $this->creerMouvement();
        $client->loginUser($fixture['utilisateur']);

        try {
            $crawler = $client->request('GET', '/stocks/mouvement/'.$fixture['mouvement'].'/supprimer');
            self::assertResponseIsSuccessful();
            $client->submit($crawler->selectButton('Confirmer la suppression')->form([
                'motif' => 'Mouvement créé sur le mauvais séjour.',
            ]));
            self::assertResponseRedirects('/stocks');

            $connexion = static::getContainer()->get(Connection::class);
            self::assertSame(0, (int) $connexion->fetchOne(
                'SELECT COUNT(*) FROM campement.mouvement_stock WHERE id = :id',
                ['id' => $fixture['mouvement']],
            ));
            $audit = $connexion->fetchAssociative(
                'SELECT action, motif, etat_avant, etat_apres FROM campement.audit_mouvement_stock WHERE mouvement_stock_id = :id',
                ['id' => $fixture['mouvement']],
            );
            self::assertIsArray($audit);
            self::assertSame('SUPPRESSION', $audit['action']);
            self::assertSame('Mouvement créé sur le mauvais séjour.', $audit['motif']);
            self::assertNotNull($audit['etat_avant']);
            self::assertNull($audit['etat_apres']);
        } finally {
            $this->supprimerFixture($fixture);
        }
    }

    /** @return array{mouvement: string, denree: string, unite: string, origine: string, utilisateur: Utilisateur, sejour: Sejour} */
    private function creerMouvement(): array
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $utilisateur = $container->get(UtilisateurRepository::class)->findOneBy(['email' => 'gestionnaire@campement.local']);
        $type = $container->get(TypeMouvementRepository::class)->findOneBy(['code' => 'ENTREE', 'actif' => true]);
        $origine = $container->get(OrigineMouvementRepository::class)->findOneBy(['code' => 'CORRECTION', 'actif' => true]);
        $unite = $container->get(UniteRepository::class)->findOneBy(['actif' => true]);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);
        self::assertInstanceOf(TypeMouvement::class, $type);
        self::assertInstanceOf(OrigineMouvement::class, $origine);
        self::assertInstanceOf(Unite::class, $unite);
        $sejour = $utilisateur->getSejoursGeres()->first();
        self::assertInstanceOf(Sejour::class, $sejour);

        $denree = (new Denree($sejour))
            ->setNom('Audit '.bin2hex(random_bytes(5)))
            ->setUniteReference($unite)
            ->setUniteInventaire($unite);
        $mouvement = new MouvementStock($sejour, $utilisateur, $type, $origine);
        $ligne = (new MouvementStockLigne($mouvement, $denree, '1.000'))
            ->setQuantiteUniteInventaire('1.000')
            ->setConditionnementSortie($unite);
        foreach ([$denree, $mouvement, $ligne] as $entite) {
            $em->persist($entite);
        }
        $em->flush();

        return [
            'mouvement' => (string) $mouvement->getId(),
            'denree' => (string) $denree->getId(),
            'unite' => (string) $unite->getId(),
            'origine' => (string) $origine->getId(),
            'utilisateur' => $utilisateur,
            'sejour' => $sejour,
        ];
    }

    /** @param array{denree: string, unite: string, origine: string} $fixture */
    private function parametresModification(array $fixture, string $token, string $motif): array
    {
        return [
            '_token' => $token,
            'type' => 'ENTREE',
            'origine' => $fixture['origine'],
            'groupe' => '',
            'fournisseur' => '',
            'motif_audit' => $motif,
            'date_navigateur' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'lignes' => [[
                'denree' => $fixture['denree'],
                'conditionnement_sortie' => $fixture['unite'],
                'quantite' => '2',
                'reference' => '',
                'numero_lot' => '',
                'conditionnements' => [],
            ]],
        ];
    }

    /** @param array{mouvement: string, denree: string} $fixture */
    private function supprimerFixture(array $fixture): void
    {
        $connexion = static::getContainer()->get(Connection::class);
        $connexion->executeStatement('DELETE FROM campement.audit_mouvement_stock WHERE mouvement_stock_id = :id', ['id' => $fixture['mouvement']]);
        $connexion->executeStatement('DELETE FROM campement.mouvement_stock WHERE id = :id', ['id' => $fixture['mouvement']]);
        $connexion->executeStatement('DELETE FROM campement.denree WHERE id = :id', ['id' => $fixture['denree']]);
    }
}
