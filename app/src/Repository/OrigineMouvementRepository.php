<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrigineMouvement;
use App\Entity\Sejour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrigineMouvement> */
final class OrigineMouvementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrigineMouvement::class);
    }

    /** @return list<OrigineMouvement> */
    public function findActifsPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.sejour = :sejour')
            ->andWhere('e.actif = true')
            ->setParameter('sejour', $sejour)
            ->orderBy('e.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
