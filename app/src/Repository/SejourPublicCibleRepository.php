<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sejour;
use App\Entity\SejourPublicCible;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SejourPublicCible> */
final class SejourPublicCibleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SejourPublicCible::class);
    }

    /** @return list<SejourPublicCible> */
    public function findActifsPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('configuration')
            ->addSelect('publicCible')
            ->join('configuration.publicCible', 'publicCible')
            ->andWhere('configuration.sejour = :sejour')
            ->andWhere('configuration.actif = true')
            ->andWhere('publicCible.actif = true')
            ->setParameter('sejour', $sejour)
            ->orderBy('publicCible.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
