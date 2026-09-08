<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/** @extends ServiceEntityRepository<Utilisateur> */
final class UtilisateurRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function loadUserByIdentifier(string $identifier): ?Utilisateur
    {
        return $this->createQueryBuilder('utilisateur')
            ->andWhere('utilisateur.email = :email')
            ->setParameter('email', mb_strtolower(trim($identifier)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Utilisateur> */
    public function findPourAdministration(): array
    {
        return $this->createQueryBuilder('utilisateur')
            ->leftJoin('utilisateur.groupe', 'groupe')->addSelect('groupe')
            ->leftJoin('utilisateur.sejoursGeres', 'sejour')->addSelect('sejour')
            ->orderBy('utilisateur.nom', 'ASC')
            ->addOrderBy('utilisateur.prenom', 'ASC')
            ->getQuery()->getResult();
    }

    public function findOneByJetonReinitialisation(string $jeton): ?Utilisateur
    {
        return $this->findOneBy(['jetonReinitialisation' => hash('sha256', $jeton)]);
    }
}
