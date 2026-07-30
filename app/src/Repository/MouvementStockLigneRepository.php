<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MouvementStockLigne;
use App\Entity\MouvementStock;
use App\Entity\Sejour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MouvementStockLigne> */
final class MouvementStockLigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementStockLigne::class);
    }

    /** @return list<MouvementStockLigne> */
    public function findPourGestion(Sejour $sejour): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('m', 'd', 'u', 'ui', 'us', 'tm', 'o', 'g', 'r', 'f')
            ->join('l.mouvementStock', 'm')->join('l.denree', 'd')->join('d.uniteReference', 'u')->join('d.uniteInventaire', 'ui')
            ->leftJoin('l.conditionnementSortie', 'us')
            ->join('m.typeMouvement', 'tm')->join('m.origineMouvement', 'o')
            ->leftJoin('m.groupe', 'g')->leftJoin('l.referenceFournisseur', 'r')->leftJoin('r.fournisseur', 'f')
            ->andWhere('m.sejour = :sejour')->setParameter('sejour', $sejour)
            ->orderBy('m.dateMouvement', 'DESC')->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findPourMouvement(MouvementStock $mouvement): ?MouvementStockLigne
    {
        return $this->findOneBy(['mouvementStock' => $mouvement]);
    }

    /** @return list<MouvementStockLigne> */
    public function findToutesPourMouvement(MouvementStock $mouvement): array
    {
        return $this->findBy(['mouvementStock' => $mouvement], ['createdAt' => 'ASC']);
    }
}
