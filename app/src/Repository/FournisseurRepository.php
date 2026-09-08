<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fournisseur;
use App\Entity\Sejour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Fournisseur> */
final class FournisseurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fournisseur::class);
    }

    /** @return list<Fournisseur> */
    public function findActifsPourSejour(Sejour $sejour): array
    {
        return $this->findPourSejour($sejour);
    }

    /** @return list<Fournisseur> */
    public function findPourSejour(Sejour $sejour, bool $inclureInactifs = false): array
    {
        $requete = $this->createQueryBuilder('fournisseur')
            ->andWhere('fournisseur.sejour = :sejour')
            ->setParameter('sejour', $sejour)
            ->orderBy('fournisseur.actif', 'DESC')
            ->addOrderBy('fournisseur.nom', 'ASC');

        if (!$inclureInactifs) {
            $requete->andWhere('fournisseur.actif = true');
        }

        return $requete->getQuery()->getResult();
    }

    public function existeAvecNomPourSejour(Sejour $sejour, string $nom, ?Fournisseur $fournisseurExclu = null): bool
    {
        $requete = $this->createQueryBuilder('fournisseur')
            ->select('fournisseur.id')
            ->andWhere('fournisseur.sejour = :sejour')
            ->andWhere('LOWER(fournisseur.nom) = LOWER(:nom)')
            ->setParameter('sejour', $sejour)
            ->setParameter('nom', $nom);

        if (null !== $fournisseurExclu) {
            $requete->andWhere('fournisseur != :fournisseurExclu')
                ->setParameter('fournisseurExclu', $fournisseurExclu);
        }

        return null !== $requete->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
