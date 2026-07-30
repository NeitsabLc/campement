<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Participant;
use App\Entity\Sejour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Participant> */
final class ParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Participant::class); }

    /** @return list<Participant> */
    public function findPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('participant')
            ->join('participant.groupe', 'groupe')->addSelect('groupe')
            ->andWhere('groupe.sejour = :sejour')->setParameter('sejour', $sejour)
            ->orderBy('groupe.nom', 'ASC')->addOrderBy('participant.type', 'ASC')
            ->addOrderBy('participant.nom', 'ASC')->addOrderBy('participant.prenom', 'ASC')
            ->getQuery()->getResult();
    }
}
