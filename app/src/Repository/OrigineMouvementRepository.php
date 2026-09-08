<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrigineMouvement;
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
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.actif = true')
            ->orderBy('e.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
