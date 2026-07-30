<?php

namespace App\Repository;

use App\Entity\Campus;
use App\Entity\Participant;
use App\Entity\Sortie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SortieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
        {
            parent::__construct($registry, Sortie::class);
        }

    public function findSortiesParCampus(
        Campus $campus,
        ?string $nomSortie,
        ?string $dateDebut,
        ?string $dateFin,
        bool $organisateur,
        bool $inscrit,
        bool $nonInscrit,
        bool $passees,
        Participant $participant
    ): array
    {
    $queryBuilder = $this->createQueryBuilder('s');

    $queryBuilder
        ->where('s.campus = :campus')
        ->setParameter('campus', $campus);

    if ($nomSortie){
        $queryBuilder
            ->andWhere('s.nom LIKE :nomSortie')
            ->setParameter('nomSortie', '%'.$nomSortie.'%');
    }

    if ($dateDebut) {
        $queryBuilder
            ->andWhere('s.dateHeureDebut >= :dateDebut')
            ->setParameter('dateDebut', $dateDebut);
    }

    if ($dateFin) {
        $queryBuilder
            ->andWhere('s.dateHeureDebut <= :dateFin')
            ->setParameter('dateFin', $dateFin);
    }

    if ($organisateur) {
        $queryBuilder
            ->andWhere('s.organisateur = :participant')
            ->setParameter('participant', $participant);
    }

    if ($inscrit) {
        $queryBuilder
            ->andWhere(':participant MEMBER OF s.inscrits')
            ->setParameter('participant', $participant);
    }

    if ($nonInscrit) {
        $queryBuilder
            ->andWhere(':participant NOT MEMBER OF s.inscrits')
            ->setParameter('participant', $participant);
    }

    if ($passees) {
        $queryBuilder
            ->andWhere('s.dateHeureDebut < :maintenant')
            ->setParameter('maintenant', new \DateTimeImmutable());
    }



    return $queryBuilder
        ->getQuery()
        ->getResult();
    }
    }
