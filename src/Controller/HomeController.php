<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CampusRepository;
use App\Repository\SortieRepository;
use App\Service\EtatSortieManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        Request                $request,
        SortieRepository       $sortieRepository,
        CampusRepository       $campusRepository,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        /** @var \App\Entity\Participant $participant */
        $participant = $this->getUser();

        foreach ($sortieRepository->findAll() as $sortie) {
            $stateManager->refresh($sortie);
        }

        /** TODO : Déplacer le flush dans une commande cron !! */
        $em->flush();

        $campusList = $campusRepository->findAll();

        $campusId = $request->query->get('campus');
        $nomSortie = $request->query->get('nom');
        $dateDebut = $request->query->get('dateDebut');
        $dateFin = $request->query->get('dateFin');
        $organisateur = $request->query->getBoolean('organisateur');
        $inscrit = $request->query->getBoolean('inscrit');
        $nonInscrit = $request->query->getBoolean('nonInscrit');
        $passees = $request->query->getBoolean('passees');
        $ouvertes = $request->query->getBoolean('ouvertes');

        if ($request->query->has('campus')) {
            $campus = $campusId ? $campusRepository->find($campusId) : null;
        } else {
            $campus = $participant->getCampus();
        }

        $sorties = $sortieRepository->findSortiesParCampus(
            $campus,
            $nomSortie,
            $dateDebut,
            $dateFin,
            $organisateur,
            $inscrit,
            $nonInscrit,
            $passees,
            $ouvertes,
            $participant
        );

        return $this->render('home/index.html.twig', [
            'sorties' => $sorties,
            'campusList' => $campusList,
        ]);
    }

}
