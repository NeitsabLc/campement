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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participant::class);
    }

    /** @return list<Participant> */
    public function findPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('participant')
            ->join('participant.groupe', 'groupe')->addSelect('groupe')
            ->leftJoin('participant.documents', 'document')->addSelect('document')
            ->andWhere('groupe.sejour = :sejour')->setParameter('sejour', $sejour)
            ->orderBy('groupe.nom', 'ASC')->addOrderBy('participant.type', 'ASC')
            ->addOrderBy('participant.nom', 'ASC')->addOrderBy('participant.prenom', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return array<string, array{jeunes: int, adultes: int}> */
    public function compterParGroupePourSejour(Sejour $sejour): array
    {
        $resultats = $this->createQueryBuilder('participant')
            ->select('IDENTITY(participant.groupe) AS groupe_id')
            ->addSelect('SUM(CASE WHEN participant.type = :jeune THEN 1 ELSE 0 END) AS jeunes')
            ->addSelect('SUM(CASE WHEN participant.type = :adulte THEN 1 ELSE 0 END) AS adultes')
            ->join('participant.groupe', 'groupe')
            ->andWhere('groupe.sejour = :sejour')
            ->setParameter('sejour', $sejour)
            ->setParameter('jeune', Participant::TYPE_JEUNE)
            ->setParameter('adulte', Participant::TYPE_ADULTE)
            ->groupBy('participant.groupe')
            ->getQuery()->getArrayResult();

        $effectifs = [];
        foreach ($resultats as $resultat) {
            $effectifs[(string) $resultat['groupe_id']] = [
                'jeunes' => (int) $resultat['jeunes'],
                'adultes' => (int) $resultat['adultes'],
            ];
        }

        return $effectifs;
    }
}
