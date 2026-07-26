<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MouvementStockLigneConditionnement;
use App\Entity\MouvementStockLigne;
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
}
