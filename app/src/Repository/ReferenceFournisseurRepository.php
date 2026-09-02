<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Denree;
use App\Entity\ReferenceFournisseur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ReferenceFournisseur> */
final class ReferenceFournisseurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReferenceFournisseur::class);
    }

    /** @return list<ReferenceFournisseur> */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')->andWhere('e.actif = true')->getQuery()->getResult();
    }

    /** @return list<ReferenceFournisseur> */
    public function findPourDenree(Denree $denree): array
    {
        return $this->createQueryBuilder('r')->addSelect('f')
            ->join('r.fournisseur', 'f')->andWhere('r.denree = :denree')
            ->setParameter('denree', $denree)->orderBy('f.nom', 'ASC')->getQuery()->getResult();
    }

    /**
     * @param list<Denree> $denrees
     *
     * @return list<ReferenceFournisseur>
     */
    public function findActifsPourDenrees(array $denrees): array
    {
        if ([] === $denrees) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->addSelect('f', 'd')
            ->join('r.fournisseur', 'f')
            ->join('r.denree', 'd')
            ->andWhere('d IN (:denrees)')
            ->andWhere('r.actif = true')
            ->andWhere('f.actif = true')
            ->setParameter('denrees', $denrees)
            ->orderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
