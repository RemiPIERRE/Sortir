<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Sortie;


class SortieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sortie::class);
    }

    public function findSortiesParCampus(Campus $campus): array
    {
        $queryBuilder = $this->createQueryBuilder('s');

        $queryBuilder
            ->where('s.campus = :campus')
            ->setParameter('campus', $campus);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
