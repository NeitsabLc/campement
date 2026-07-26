<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Groupe;
use App\Entity\Sejour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Groupe> */
final class GroupeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Groupe::class);
    }

    /** @return list<Groupe> */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')->andWhere('e.actif = true')->getQuery()->getResult();
    }

    /** @return list<Groupe> */
    public function findActifsPourSejour(Sejour $sejour): array
    {
        return $this->findPourSejour($sejour);
    }

    /** @return list<Groupe> */
    public function findPourSejour(Sejour $sejour, bool $inclureInactifs = false): array
    {
        $requete = $this->createQueryBuilder('groupe')
            ->andWhere('groupe.sejour = :sejour')
            ->setParameter('sejour', $sejour)
            ->orderBy('groupe.actif', 'DESC')
            ->addOrderBy('groupe.nom', 'ASC');

        if (!$inclureInactifs) {
            $requete->andWhere('groupe.actif = true');
        }

        return $requete->getQuery()->getResult();
    }

    public function existeAvecNomPourSejour(Sejour $sejour, string $nom, ?Groupe $groupeExclu = null): bool
    {
        $requete = $this->createQueryBuilder('groupe')
            ->select('groupe.id')
            ->andWhere('groupe.sejour = :sejour')
            ->andWhere('LOWER(groupe.nom) = LOWER(:nom)')
            ->setParameter('sejour', $sejour)
            ->setParameter('nom', $nom);

        if (null !== $groupeExclu) {
            $requete->andWhere('groupe != :groupeExclu')->setParameter('groupeExclu', $groupeExclu);
        }

        return null !== $requete->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
