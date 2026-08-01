<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Denree;
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

    /**
     * @param list<Denree> $denrees
     *
     * @return list<ReferenceFournisseurConditionnement>
     */
    public function findActifsPourDenrees(array $denrees): array
    {
        if ([] === $denrees) {
            return [];
        }

        return $this->createQueryBuilder('niveau')
            ->addSelect('reference', 'denree', 'conditionnement', 'uniteContenu')
            ->join('niveau.referenceFournisseur', 'reference')
            ->join('reference.denree', 'denree')
            ->join('niveau.conditionnement', 'conditionnement')
            ->leftJoin('niveau.uniteContenu', 'uniteContenu')
            ->andWhere('denree IN (:denrees)')
            ->andWhere('reference.actif = true')
            ->join('reference.fournisseur', 'fournisseur')
            ->andWhere('fournisseur.actif = true')
            ->setParameter('denrees', $denrees)
            ->orderBy('niveau.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
