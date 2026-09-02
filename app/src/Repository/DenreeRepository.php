<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Denree;
use App\Entity\Sejour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Denree> */
final class DenreeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Denree::class);
    }

    /** @return list<Denree> */
    public function findActifsPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('unite')
            ->join('e.uniteReference', 'unite')
            ->andWhere('e.sejour = :sejour')
            ->andWhere('e.actif = true')
            ->setParameter('sejour', $sejour)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Denree> */
    public function findPourGestion(Sejour $sejour, bool $actif): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('u', 'ui')
            ->join('d.uniteReference', 'u')
            ->join('d.uniteInventaire', 'ui')
            ->andWhere('d.sejour = :sejour')
            ->andWhere('d.actif = :actif')
            ->setParameter('sejour', $sejour)
            ->setParameter('actif', $actif)
            ->orderBy('d.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existeAvecNomPourSejour(Sejour $sejour, string $nom, ?Denree $exclue = null): bool
    {
        $qb = $this->createQueryBuilder('d')->select('COUNT(d.id)')
            ->andWhere('d.sejour = :sejour')->andWhere('LOWER(d.nom) = LOWER(:nom)')
            ->setParameter('sejour', $sejour)->setParameter('nom', $nom);
        if (null !== $exclue) {
            $qb->andWhere('d != :exclue')->setParameter('exclue', $exclue);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
