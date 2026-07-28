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

    /** @return list<array{denree: Denree, stockEntree: string, stockSortie: string}> */
    public function findPourGestion(Sejour $sejour, bool $actif): array
    {
        $lignes = $this->createQueryBuilder('d')
            ->addSelect('u', 'ui')
            ->addSelect("COALESCE(SUM(CASE WHEN tm.code = 'ENTREE' THEN l.quantiteUniteReference ELSE 0 END), 0) AS stockEntree")
            ->addSelect("COALESCE(SUM(CASE WHEN tm.code = 'SORTIE' THEN l.quantiteUniteReference ELSE 0 END), 0) AS stockSortie")
            ->join('d.uniteReference', 'u')
            ->join('d.uniteInventaire', 'ui')
            ->leftJoin(\App\Entity\MouvementStockLigne::class, 'l', 'WITH', 'l.denree = d')
            ->leftJoin('l.mouvementStock', 'm')
            ->leftJoin('m.typeMouvement', 'tm')
            ->andWhere('d.sejour = :sejour')
            ->andWhere('d.actif = :actif')
            ->setParameter('sejour', $sejour)
            ->setParameter('actif', $actif)
            ->groupBy('d.id, u.id, ui.id')
            ->orderBy('d.nom', 'ASC')
            ->getQuery()->getResult();

        return array_map(static fn (array $ligne): array => [
            'denree' => $ligne[0],
            'stockEntree' => (string) $ligne['stockEntree'],
            'stockSortie' => (string) $ligne['stockSortie'],
        ], $lignes);
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
