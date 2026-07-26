<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReferenceFournisseur;
use App\Entity\ReferenceFournisseurConditionnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ReferenceFournisseurConditionnement> */
final class ReferenceFournisseurConditionnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReferenceFournisseurConditionnement::class);
    }

    /** @return list<ReferenceFournisseurConditionnement> */
    public function findPourReference(ReferenceFournisseur $reference): array
    {
        return $this->findBy(['referenceFournisseur' => $reference], ['ordre' => 'ASC']);
    }
}
