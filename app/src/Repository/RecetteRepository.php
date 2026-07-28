<?php
declare(strict_types=1);
namespace App\Repository;
use App\Entity\Recette; use App\Entity\Sejour; use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; use Doctrine\Persistence\ManagerRegistry;
/** @extends ServiceEntityRepository<Recette> */
final class RecetteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r) { parent::__construct($r, Recette::class); }
    /** @return list<Recette> */ public function findActivesPourSejour(Sejour $s): array { return $this->createQueryBuilder('r')->addSelect('l','d','u','q','p')->leftJoin('r.denrees','l')->leftJoin('l.denree','d')->leftJoin('l.conditionnement','u')->leftJoin('l.quantites','q')->leftJoin('q.sejourPublicCible','p')->andWhere('r.sejour = :s')->andWhere('r.actif = true')->setParameter('s',$s)->orderBy('r.nom','ASC')->getQuery()->getResult(); }
}
