<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Groupe;
use App\Entity\GroupeRepas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GroupeRepas> */
final class GroupeRepasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupeRepas::class);
    }

    /** @return list<GroupeRepas> */
    public function findPourGroupe(Groupe $groupe): array
    {
        return $this->createQueryBuilder('configuration')
            ->addSelect('menu', 'repas', 'typeRepas')
            ->join('configuration.menu', 'menu')
            ->leftJoin('menu.sejourTypeRepas', 'repas')
            ->leftJoin('repas.typeRepas', 'typeRepas')
            ->andWhere('configuration.groupe = :groupe')
            ->setParameter('groupe', $groupe)
            ->orderBy('menu.dateMenu', 'ASC')
            ->addOrderBy('repas.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<Groupe> $groupes
     *
     * @return list<GroupeRepas>
     */
    public function findPourGroupes(array $groupes): array
    {
        if ([] === $groupes) {
            return [];
        }

        return $this->createQueryBuilder('configuration')
            ->addSelect('groupe', 'menu')
            ->join('configuration.groupe', 'groupe')
            ->join('configuration.menu', 'menu')
            ->andWhere('configuration.groupe IN (:groupes)')
            ->setParameter('groupes', $groupes)
            ->getQuery()
            ->getResult();
    }
}
