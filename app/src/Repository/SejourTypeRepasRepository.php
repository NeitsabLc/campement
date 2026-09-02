<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SejourTypeRepas> */
final class SejourTypeRepasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SejourTypeRepas::class);
    }

    /** @return list<SejourTypeRepas> */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')->andWhere('e.actif = true')->getQuery()->getResult();
    }

    /** @return list<SejourTypeRepas> */
    public function findActifsPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('repasSejour')
            ->addSelect('typeRepas')
            ->join('repasSejour.typeRepas', 'typeRepas')
            ->andWhere('repasSejour.sejour = :sejour')
            ->andWhere('repasSejour.actif = true')
            ->andWhere('typeRepas.actif = true')
            ->setParameter('sejour', $sejour)
            ->orderBy('repasSejour.ordre', 'ASC')
            ->addOrderBy('typeRepas.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<SejourTypeRepas> */
    public function findPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('repasSejour')
            ->addSelect('typeRepas')
            ->join('repasSejour.typeRepas', 'typeRepas')
            ->andWhere('repasSejour.sejour = :sejour')
            ->setParameter('sejour', $sejour)
            ->orderBy('repasSejour.ordre', 'ASC')
            ->addOrderBy('typeRepas.ordre', 'ASC')
            ->getQuery()->getResult();
    }
}
