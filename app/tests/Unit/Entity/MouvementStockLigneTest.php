<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Denree;
use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Entity\OrigineMouvement;
use App\Entity\Sejour;
use App\Entity\TypeMouvement;
use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

final class MouvementStockLigneTest extends TestCase
{
    public function testNumeroLotEstNettoyeEtPeutEtreEfface(): void
    {
        $ligne = $this->creerLigne();

        $ligne->setNumeroLot('  LOT-24-A  ');
        self::assertSame('LOT-24-A', $ligne->getNumeroLot());

        $ligne->setNumeroLot('   ');
        self::assertNull($ligne->getNumeroLot());
    }

    public function testNumeroLotEstLimiteACentCaracteres(): void
    {
        $ligne = $this->creerLigne();

        $this->expectException(\InvalidArgumentException::class);
        $ligne->setNumeroLot(str_repeat('A', 101));
    }

    private function creerLigne(): MouvementStockLigne
    {
        $sejour = new Sejour('Test', new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-07-15'));
        $denree = new Denree($sejour);
        $mouvement = new MouvementStock(
            $sejour,
            $this->createStub(Utilisateur::class),
            new TypeMouvement('ENTREE', 'Entrée'),
            new OrigineMouvement('FOURNISSEUR', 'Fournisseur'),
        );

        return new MouvementStockLigne($mouvement, $denree, '1.000');
    }
}
