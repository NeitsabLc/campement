<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sejour;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Sejour> */
final class SejourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sejour::class);
    }

    public function findActif(): ?Sejour
    {
        return $this->createQueryBuilder('sejour')
            ->andWhere('sejour.actif = true')
            ->orderBy('sejour.dateDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Sejour> */
    public function findAAnonymiser(\DateTimeImmutable $dateLimite): array
    {
        return $this->createQueryBuilder('sejour')
            ->leftJoin('sejour.gestionnaires', 'gestionnaire')->addSelect('gestionnaire')
            ->andWhere('sejour.dateFin <= :limite')
            // Un séjour réactivé doit être retraité même s'il conserve une
            // ancienne marque d'anonymisation provenant de sa désactivation.
            ->andWhere('(sejour.anonymiseAt IS NULL OR sejour.actif = true)')
            ->setParameter('limite', $dateLimite)
            ->orderBy('sejour.dateFin', 'ASC')
            ->getQuery()->getResult();
    }

    public function findPourDistributionPublique(string $jeton): ?Sejour
    {
        return $this->createQueryBuilder('sejour')
            ->andWhere('sejour.jetonDistributionPublique = :jeton')
            ->andWhere('sejour.actif = true')
            ->andWhere('sejour.moduleIntendanceActif = true')
            ->andWhere('sejour.distributionPubliqueActive = true')
            ->setParameter('jeton', $jeton)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<Sejour> */
    public function findActifsPourUtilisateur(Utilisateur $utilisateur, bool $administrateur): array
    {
        $qb = $this->createQueryBuilder('sejour')
            ->andWhere('sejour.actif = true')
            ->orderBy('sejour.dateDebut', 'DESC');
        if (!$administrateur) {
            $qb->join('sejour.gestionnaires', 'gestionnaire')
                ->andWhere('gestionnaire = :utilisateur')
                ->setParameter('utilisateur', $utilisateur);
        }
        return $qb->getQuery()->getResult();
    }
}
