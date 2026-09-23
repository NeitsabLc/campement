<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommentaireStagePratique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CommentaireStagePratique> */
final class CommentaireStagePratiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentaireStagePratique::class);
    }
}
