<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Menu;
use App\Entity\Sejour;
use App\Entity\SejourTypeRepas;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Menu> */
final class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    /** @return list<Menu> */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')->andWhere('e.actif = true')->getQuery()->getResult();
    }

    public function findPourRepas(Sejour $sejour, DateTimeImmutable $date, SejourTypeRepas $repas): ?Menu
    {
        return $this->createQueryBuilder('menu')
            ->addSelect('menuDenree', 'denree', 'quantite', 'sejourPublicCible', 'publicCible')
            ->leftJoin('menu.denrees', 'menuDenree')
            ->leftJoin('menuDenree.denree', 'denree')
            ->leftJoin('menuDenree.quantites', 'quantite')
            ->leftJoin('quantite.sejourPublicCible', 'sejourPublicCible')
            ->leftJoin('sejourPublicCible.publicCible', 'publicCible')
            ->andWhere('menu.sejour = :sejour')
            ->andWhere('menu.dateMenu = :date')
            ->andWhere('menu.sejourTypeRepas = :repas')
            ->andWhere('menu.actif = true')
            ->setParameter('sejour', $sejour)
            ->setParameter('date', $date)
            ->setParameter('repas', $repas)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Menu> */
    public function findActifsPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('menu')
            ->addSelect('repas', 'typeRepas', 'menuDenree', 'denree', 'quantite', 'sejourPublicCible', 'publicCible', 'unite')
            ->join('menu.sejourTypeRepas', 'repas')
            ->join('repas.typeRepas', 'typeRepas')
            ->leftJoin('menu.denrees', 'menuDenree')
            ->leftJoin('menuDenree.denree', 'denree')
            ->leftJoin('denree.uniteReference', 'unite')
            ->leftJoin('menuDenree.quantites', 'quantite')
            ->leftJoin('quantite.sejourPublicCible', 'sejourPublicCible')
            ->leftJoin('sejourPublicCible.publicCible', 'publicCible')
            ->andWhere('menu.sejour = :sejour')
            ->andWhere('menu.actif = true')
            ->andWhere('repas.actif = true')
            ->andWhere('sejourPublicCible.id IS NULL OR (sejourPublicCible.actif = true AND publicCible.actif = true)')
            ->setParameter('sejour', $sejour)
            ->orderBy('menu.dateMenu', 'ASC')
            ->addOrderBy('repas.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
