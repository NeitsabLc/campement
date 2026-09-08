<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Denree;
use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
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
            ->leftJoin('l.conditionnementSaisie', 'us')
            ->join('m.typeMouvement', 'tm')->join('m.origineMouvement', 'o')
            ->leftJoin('m.groupe', 'g')->leftJoin('l.referenceFournisseur', 'r')->leftJoin('r.fournisseur', 'f')
            ->andWhere('m.sejour = :sejour')->setParameter('sejour', $sejour)
            ->orderBy('m.dateMouvement', 'DESC')->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return list<MouvementStockLigne> */
    public function findPourDenree(Denree $denree): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('m', 'tm', 'o', 'us')
            ->join('l.mouvementStock', 'm')
            ->join('m.typeMouvement', 'tm')
            ->join('m.origineMouvement', 'o')
            ->leftJoin('l.conditionnementSaisie', 'us')
            ->andWhere('l.denree = :denree')
            ->setParameter('denree', $denree)
            ->orderBy('m.dateMouvement', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPourMouvement(MouvementStock $mouvement): ?MouvementStockLigne
    {
        return $this->findOneBy(['mouvementStock' => $mouvement]);
    }

    /** @return list<MouvementStockLigne> */
    public function findToutesPourMouvement(MouvementStock $mouvement): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('d', 'u', 'ui', 'us', 'r', 'f')
            ->join('l.denree', 'd')
            ->join('d.uniteReference', 'u')
            ->join('d.uniteInventaire', 'ui')
            ->leftJoin('l.conditionnementSaisie', 'us')
            ->leftJoin('l.referenceFournisseur', 'r')
            ->leftJoin('r.fournisseur', 'f')
            ->andWhere('l.mouvementStock = :mouvement')
            ->setParameter('mouvement', $mouvement)
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
