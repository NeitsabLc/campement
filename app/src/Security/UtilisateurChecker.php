<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UtilisateurChecker implements UserCheckerInterface
{
    private const MESSAGE_REFUS = 'Identifiants incorrects.';

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if (!$user->isActif() || Utilisateur::ROLE_TECHNIQUE === $user->getRole()) {
            throw new CustomUserMessageAccountStatusException(self::MESSAGE_REFUS);
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
