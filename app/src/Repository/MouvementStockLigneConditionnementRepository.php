<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MouvementStockLigne;
use App\Entity\MouvementStockLigneConditionnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MouvementStockLigneConditionnement> */
final class MouvementStockLigneConditionnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementStockLigneConditionnement::class);
    }

    /** @return list<MouvementStockLigneConditionnement> */
    public function findPourLigne(MouvementStockLigne $ligne): array
    {
        return $this->findBy(['mouvementStockLigne' => $ligne]);
    }

    /**
     * @param list<MouvementStockLigne> $lignes
     *
     * @return list<MouvementStockLigneConditionnement>
     */
    public function findPourLignes(array $lignes): array
    {
        if ([] === $lignes) {
            return [];
        }

        return $this->createQueryBuilder('detail')
            ->addSelect('ligne', 'conditionnement')
            ->join('detail.mouvementStockLigne', 'ligne')
            ->join('detail.conditionnement', 'conditionnement')
            ->andWhere('ligne IN (:lignes)')
            ->setParameter('lignes', $lignes)
            ->getQuery()
            ->getResult();
    }
}
