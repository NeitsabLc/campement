<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sejour;
use App\Entity\SituationParticuliere;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SituationParticuliere> */
final class SituationParticuliereRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SituationParticuliere::class); }

    /** @return list<SituationParticuliere> */
    public function findPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('situation')
            ->leftJoin('situation.taches', 'tache')->addSelect('tache')
            ->leftJoin('situation.participants', 'participant')->addSelect('participant')
            ->andWhere('situation.sejour = :sejour')->setParameter('sejour', $sejour)
            ->orderBy('situation.dateSituation', 'DESC')->addOrderBy('situation.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
