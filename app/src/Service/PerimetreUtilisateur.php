<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Sejour;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Request;

final class PerimetreUtilisateur
{
    /** @param list<Sejour> $sejoursAccessibles */
    public function selectionnerSejour(Request $request, array $sejoursAccessibles): ?Sejour
    {
        $idDemande = $request->query->getString('sejour');
        if ('' === $idDemande) $idDemande = (string) $request->getSession()->get('utilisateurs_sejour', '');
        foreach ($sejoursAccessibles as $sejour) {
            if ((string) $sejour->getId() === $idDemande) {
                $request->getSession()->set('utilisateurs_sejour', $idDemande);
                return $sejour;
            }
        }
        $sejour = $sejoursAccessibles[0] ?? null;
        if ($sejour instanceof Sejour) $request->getSession()->set('utilisateurs_sejour', (string) $sejour->getId());
        return $sejour instanceof Sejour ? $sejour : null;
    }

    /** @param list<Sejour> $sejours */
    public function contientSejour(array $sejours, Sejour $recherche): bool
    {
        return array_any($sejours, static fn (Sejour $sejour): bool => $sejour === $recherche);
    }

    public function utilisateurEstVisible(Utilisateur $utilisateur, ?Sejour $sejour, bool $estAdministrateur): bool
    {
        if (Utilisateur::ROLE_TECHNIQUE === $utilisateur->getRole()) return false;
        if ($estAdministrateur) return true;
        if (null === $sejour) return false;
        return (Utilisateur::ROLE_GESTIONNAIRE === $utilisateur->getRole() && $utilisateur->getSejoursGeres()->contains($sejour))
            || (Utilisateur::ROLE_GROUPE === $utilisateur->getRole() && $utilisateur->getGroupe()?->getSejour() === $sejour);
    }

    public function possedeAutreSejour(Utilisateur $utilisateur, ?Sejour $sejour): bool
    {
        foreach ($utilisateur->getSejoursGeres() as $sejourGere) {
            if ($sejourGere !== $sejour) return true;
        }
        return false;
    }
}
