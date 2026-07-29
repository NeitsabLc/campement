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
            ->addSelect('menuDenree', 'denree', 'conditionnement', 'quantite', 'sejourPublicCible', 'publicCible')
            ->leftJoin('menu.denrees', 'menuDenree')
            ->leftJoin('menuDenree.denree', 'denree')
            ->leftJoin('menuDenree.conditionnement', 'conditionnement')
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

    /**
     * @return list<array{specialCode: ?string, dateMenu: ?DateTimeImmutable, repasId: ?string, nombreDenrees: string|int}>
     */
    public function findStatutsPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('menu')
            ->select('menu.specialCode AS specialCode', 'menu.dateMenu AS dateMenu')
            ->addSelect('IDENTITY(menu.sejourTypeRepas) AS repasId', 'COUNT(menuDenree.id) AS nombreDenrees')
            ->leftJoin('menu.sejourTypeRepas', 'repas')
            ->leftJoin('menu.denrees', 'menuDenree')
            ->andWhere('menu.sejour = :sejour')
            ->andWhere('menu.actif = true')
            ->andWhere('menu.specialCode IS NOT NULL OR repas.actif = true')
            ->setParameter('sejour', $sejour)
            ->groupBy('menu.id', 'menu.specialCode', 'menu.dateMenu', 'menu.sejourTypeRepas')
            ->getQuery()
            ->getArrayResult();
    }

    public function findSpecial(Sejour $sejour, string $code): ?Menu
    {
        return $this->createQueryBuilder('menu')->addSelect('menuDenree','denree','quantite','sejourPublicCible','publicCible','conditionnement')
            ->leftJoin('menu.denrees','menuDenree')->leftJoin('menuDenree.denree','denree')->leftJoin('menuDenree.conditionnement','conditionnement')
            ->leftJoin('menuDenree.quantites','quantite')->leftJoin('quantite.sejourPublicCible','sejourPublicCible')->leftJoin('sejourPublicCible.publicCible','publicCible')
            ->andWhere('menu.sejour = :sejour')->andWhere('menu.specialCode = :code')->andWhere('menu.actif = true')
            ->setParameter('sejour',$sejour)->setParameter('code',$code)->getQuery()->getOneOrNullResult();
    }

    /** @return list<Menu> */
    public function findActifsPourSejour(Sejour $sejour): array
    {
        return $this->createQueryBuilder('menu')
            ->addSelect('repas', 'typeRepas', 'menuDenree', 'denree', 'quantite', 'sejourPublicCible', 'publicCible', 'unite')
            ->leftJoin('menu.sejourTypeRepas', 'repas')
            ->leftJoin('repas.typeRepas', 'typeRepas')
            ->leftJoin('menu.denrees', 'menuDenree')
            ->leftJoin('menuDenree.denree', 'denree')
            ->leftJoin('denree.uniteReference', 'unite')
            ->leftJoin('menuDenree.quantites', 'quantite')
            ->leftJoin('quantite.sejourPublicCible', 'sejourPublicCible')
            ->leftJoin('sejourPublicCible.publicCible', 'publicCible')
            ->andWhere('menu.sejour = :sejour')
            ->andWhere('menu.actif = true')
            ->andWhere('menu.specialCode IS NOT NULL OR repas.actif = true')
            ->andWhere('sejourPublicCible.id IS NULL OR (sejourPublicCible.actif = true AND publicCible.actif = true)')
            ->setParameter('sejour', $sejour)
            ->orderBy('menu.specialCode', 'ASC')->addOrderBy('menu.dateMenu', 'ASC')
            ->addOrderBy('repas.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
