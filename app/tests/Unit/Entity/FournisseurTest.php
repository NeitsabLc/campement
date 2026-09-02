<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Fournisseur;
use App\Entity\Sejour;
use PHPUnit\Framework\TestCase;

final class FournisseurTest extends TestCase
{
    public function testLAdresseEstLimiteeCoteMetier(): void
    {
        $sejour = new Sejour('Test', new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-31'));
        $fournisseur = new Fournisseur($sejour, 'Fournisseur');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('1 000 caractères');
        $fournisseur->setAdresse(str_repeat('a', Fournisseur::ADRESSE_LONGUEUR_MAX + 1));
    }
}
