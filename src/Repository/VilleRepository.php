<?php

namespace App\Repository;

use App\Entity\Ville;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


/**
 * Dépôt des villes.
 *
 * @extends ServiceEntityRepository<Ville>
 */
class VilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ville::class);
    }
}
