<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TypeRepas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TypeRepas> */
final class TypeRepasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeRepas::class);
    }

    /** @return list<TypeRepas> */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')->andWhere('e.actif = true')->getQuery()->getResult();
    }
}
