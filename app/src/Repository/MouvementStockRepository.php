<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MouvementStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MouvementStock> */
final class MouvementStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementStock::class);
    }

    public function findPourFormulaire(string $id): ?MouvementStock
    {
        return $this->createQueryBuilder('m')
            ->addSelect('s', 'tm', 'o', 'g')
            ->join('m.sejour', 's')
            ->join('m.typeMouvement', 'tm')
            ->join('m.origineMouvement', 'o')
            ->leftJoin('m.groupe', 'g')
            ->andWhere('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
