<?php

namespace App\Controller;

use App\Repository\CampusRepository;
use App\Repository\SortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        SortieRepository $sortieRepository,
        CampusRepository $campusRepository

    ): Response
    {


        $campusList = $campusRepository->findAll();

        $campusId = $request->query->get('campus');

        $nomSortie = $request->query->get('nom');

        $dateDebut = $request->query->get('dateDebut');

        $dateFin = $request->query->get('dateFin');

        $organisateur = $request->query->getBoolean('organisateur');

        $inscrit = $request->query->getBoolean('inscrit');

        $nonInscrit = $request->query->getBoolean('nonInscrit');

        $passees = $request->query->getBoolean('passees');

        $participant = $this->getUser();

        if ($campusId){
            $campus = $campusRepository->find($campusId);

            $sorties = $sortieRepository->findSortiesParCampus(
                $campus,
                $nomSortie,
                $dateDebut,
                $dateFin,
                $organisateur,
                $inscrit,
                $nonInscrit,
                $passees,
                $participant
            );
        } else {
            $campus = $this->getUser()->getCampus();

            $sorties = $sortieRepository->findSortiesParCampus(
                $campus,
                $nomSortie,
                $dateDebut,
                $dateFin,
                $organisateur,
                $inscrit,
                $nonInscrit,
                $passees,
                $participant
            );

        }


        return $this->render('home/index.html.twig', [
            'sorties' => $sorties,
            'campusList' => $campusList,
        ]);
    }
}
