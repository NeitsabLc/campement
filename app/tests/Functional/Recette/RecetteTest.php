<?php

declare(strict_types=1);

namespace App\Tests\Functional\Recette;

use App\Entity\Recette;
use App\Entity\Sejour;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RecetteTest extends WebTestCase
{
    public function testDeuxRecettesNePeuventPasPorterLeMemeNomPourUnSejour(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $utilisateur = $container->get(UtilisateurRepository::class)
            ->findOneBy(['email' => 'gestionnaire@campement.local']);
        self::assertNotNull($utilisateur);
        $client->loginUser($utilisateur);

        $sejour = $utilisateur->getDernierSejour();
        if (!$sejour instanceof Sejour || !$sejour->isActif()) {
            $sejour = $utilisateur->getSejoursGeres()->filter(static fn (Sejour $candidat): bool => $candidat->isActif())->first();
        }
        self::assertInstanceOf(Sejour::class, $sejour);

        $nom = 'Recette unique '.bin2hex(random_bytes(4));
        $recetteExistante = (new Recette($sejour))->setNom($nom);
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($recetteExistante);
        $entityManager->flush();

        $crawler = $client->request('GET', '/recettes/ajouter');
        $client->request('POST', '/recettes/ajouter', [
            '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
            'nom' => mb_strtoupper($nom),
            'categorie' => 'PLAT',
            'lignes' => [],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Une recette portant ce nom existe déjà pour ce séjour.');
    }
}
