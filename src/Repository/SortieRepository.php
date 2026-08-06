<?php

namespace App\Repository;

use App\Entity\Campus;
use App\Entity\Participant;
use App\Entity\Sortie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Dépôt des sorties.
 *
 * @extends ServiceEntityRepository<Sortie>
 */
class SortieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sortie::class);
    }

    /**
     * Recherche les sorties selon les critères du filtre d'accueil.
     *
     * Exclut toujours les sorties « En création ». Les indicateurs $inscrit et
     * $nonInscrit sont mutuellement exclusifs (si les deux sont vrais, aucun n'est appliqué).
     *
     * @param string|null $dateDebut Borne basse au format Y-m-d (incluse)
     * @param string|null $dateFin Borne haute au format Y-m-d (exclue au lendemain)
     *
     * @return Sortie[]
     */
    public function findSortiesParCampus(
        ?Campus     $campus,
        ?string     $nomSortie,
        ?string     $dateDebut,
        ?string     $dateFin,
        bool        $organisateur,
        bool        $inscrit,
        bool        $nonInscrit,
        bool        $passees,
        bool        $ouvertes,
        Participant $participant
    ): array {
        $queryBuilder = $this->createQueryBuilder('s');

        $queryBuilder
            ->join('s.etat', 'e')
            ->andWhere('e.libelle != :etatCreation OR s.organisateur = :participant')
            ->setParameter('etatCreation', 'En création')
            ->setParameter('participant', $participant);

        if ($campus) {
            $queryBuilder
                ->andWhere('s.campus = :campus')
                ->setParameter('campus', $campus);
        }

        if ($nomSortie) {
            $queryBuilder
                ->andWhere('s.nom LIKE :nomSortie')
                ->setParameter('nomSortie', '%' . $nomSortie . '%');
        }

        if ($dateDebut) {
            $queryBuilder
                ->andWhere('s.dateHeureDebut >= :dateDebut')
                ->setParameter('dateDebut', new \DateTimeImmutable($dateDebut));
        }

        if ($dateFin) {
            $dateFinExclue = (new \DateTimeImmutable($dateFin))->modify('+1 day');
            $queryBuilder
                ->andWhere('s.dateHeureDebut < :dateFin')
                ->setParameter('dateFin', $dateFinExclue);
        }

        if ($organisateur) {
            $queryBuilder
                ->andWhere('s.organisateur = :participant')
                ->setParameter('participant', $participant);
        }

        if ($inscrit && !$nonInscrit) {
            $queryBuilder
                ->andWhere(':participant MEMBER OF s.inscrits')
                ->setParameter('participant', $participant);
        }

        if ($nonInscrit && !$inscrit) {
            $queryBuilder
                ->andWhere(':participant NOT MEMBER OF s.inscrits')
                ->setParameter('participant', $participant);
        }

        if ($passees) {
            $queryBuilder
                ->andWhere('s.dateHeureDebut < :maintenant')
                ->setParameter('maintenant', new \DateTimeImmutable());
        }

        if ($ouvertes) {
            $queryBuilder
                ->andWhere('e.libelle = :ouverte')
                ->setParameter('ouverte', 'Ouverte');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste des sorties pour l'API : filtrable par état et par date prévue.
     *
     * Exclut toujours les sorties « En création » et « Terminée ».
     *
     * @return Sortie[]
     */
    public function findForApi(?string $etat, ?\DateTimeImmutable $date): array
    {
        $queryBuilder = $this->createQueryBuilder('s');

        $queryBuilder
            ->join('s.etat', 'e')
            ->andWhere('e.libelle NOT IN (:etatsExclus)')
            ->setParameter('etatsExclus', ['En création', 'Terminée'])
            ->orderBy('s.dateHeureDebut', 'ASC');

        if ($etat) {
            $queryBuilder
                ->andWhere('e.libelle = :etat')
                ->setParameter('etat', $etat);
        }

        if ($date) {
            $dateFin = $date->modify('+1 day');
            $queryBuilder
                ->andWhere('s.dateHeureDebut >= :dateDebut')
                ->andWhere('s.dateHeureDebut < :dateFin')
                ->setParameter('dateDebut', $date)
                ->setParameter('dateFin', $dateFin);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
