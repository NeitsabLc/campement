<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Utilisateur;
use App\Security\UtilisateurChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UtilisateurCheckerTest extends TestCase
{
    public function testUnUtilisateurActifAvecUnRoleMetierEstAccepte(): void
    {
        $utilisateur = (new Utilisateur())
            ->setRole(Utilisateur::ROLE_ADMIN)
            ->setActif(true);

        (new UtilisateurChecker())->checkPreAuth($utilisateur);

        self::addToAssertionCount(1);
    }

    #[DataProvider('fournitUtilisateurRefuse')]
    public function testUnUtilisateurInactifOuTechniqueEstRefuse(string $role, bool $actif): void
    {
        $utilisateur = (new Utilisateur())
            ->setRole($role)
            ->setActif($actif);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Identifiants incorrects.');

        (new UtilisateurChecker())->checkPreAuth($utilisateur);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function fournitUtilisateurRefuse(): iterable
    {
        yield 'compte inactif' => [Utilisateur::ROLE_ADMIN, false];
        yield 'compte technique actif' => [Utilisateur::ROLE_TECHNIQUE, true];
    }
}
