<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Recette;
use App\Entity\Sejour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Recette> */
final class RecetteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, Recette::class);
    }

    /** @return list<Recette> */
    public function findActivesPourSejour(Sejour $s): array
    {
        return $this->findPourGestion($s, true);
    }

    /** @return list<Recette> */
    public function findPourGestion(Sejour $sejour, bool $actif, string $tri = 'nom', string $ordre = 'asc'): array
    {
        $colonneTri = 'categorie' === $tri ? 'r.categorie' : 'r.nom';
        $directionTri = 'desc' === mb_strtolower($ordre) ? 'DESC' : 'ASC';

        $requete = $this->createQueryBuilder('r')
            ->addSelect('l', 'd', 'u', 'q', 'p')
            ->leftJoin('r.denrees', 'l')
            ->leftJoin('l.denree', 'd')
            ->leftJoin('l.conditionnement', 'u')
            ->leftJoin('l.quantites', 'q')
            ->leftJoin('q.sejourPublicCible', 'p')
            ->andWhere('r.sejour = :sejour')
            ->andWhere('r.actif = :actif')
            ->setParameter('sejour', $sejour)
            ->setParameter('actif', $actif)
            ->orderBy($colonneTri, $directionTri);
        if ('categorie' === $tri) {
            $requete->addOrderBy('r.nom', 'ASC');
        }

        return $requete->getQuery()->getResult();
    }
}
