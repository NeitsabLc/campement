<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Unite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Unite> */
final class UniteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unite::class);
    }

    /** @return list<Unite> */
    public function findActifs(): array
    {
        $unites = $this->createQueryBuilder('e')
            ->andWhere('e.actif = true')
            ->getQuery()
            ->getResult();

        $collator = new \Collator('fr_FR');
        usort($unites, static fn (Unite $a, Unite $b): int => $collator->compare($a->getNom(), $b->getNom()));

        return $unites;
    }
}
