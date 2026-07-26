<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PublicCible;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicCible>
 */
final class PublicCibleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicCible::class);
    }

    /** @return list<PublicCible> */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('publicCible')
            ->andWhere('publicCible.actif = true')
            ->orderBy('publicCible.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
