<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Repository\SejourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class ContexteSejour
{
    public function __construct(
        private readonly Security $security,
        private readonly SejourRepository $sejours,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @return list<Sejour> */
    public function accessibles(): array
    {
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) { return []; }
        if ($this->security->isGranted(Utilisateur::ROLE_GROUPE)) {
            $sejour = $utilisateur->getGroupe()?->getSejour();

            return $sejour instanceof Sejour && $sejour->isActif() ? [$sejour] : [];
        }
        return $this->sejours->findActifsPourUtilisateur($utilisateur, $this->security->isGranted(Utilisateur::ROLE_ADMIN));
    }

    public function actif(): ?Sejour
    {
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) { return null; }
        $accessibles = $this->accessibles();
        $selection = $utilisateur->getDernierSejour();
        if ($selection instanceof Sejour && $selection->isActif() && array_any($accessibles, static fn (Sejour $s): bool => $s === $selection)) {
            return $selection;
        }
        if (1 === count($accessibles)) {
            $this->selectionner($accessibles[0]);
            return $accessibles[0];
        }
        if (null !== $selection) { $this->selectionner(null); }
        return null;
    }

    public function selectionner(?Sejour $sejour): void
    {
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) { return; }
        if (null !== $sejour && (!$sejour->isActif() || !array_any($this->accessibles(), static fn (Sejour $s): bool => $s === $sejour))) {
            throw new \InvalidArgumentException('Ce séjour n’est pas accessible.');
        }
        if ($utilisateur->getDernierSejour() !== $sejour) {
            $utilisateur->setDernierSejour($sejour);
            $this->entityManager->flush();
        }
    }
}
