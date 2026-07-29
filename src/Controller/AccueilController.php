<?php

namespace App\Controller;

use App\Repository\CampusRepository;
use App\Repository\SortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


final class AccueilController extends AbstractController
{
    #[Route('/accueil', name: 'app_accueil')]
    public function index(SortieRepository $sortieRepository, CampusRepository $campusRepository): Response
    {
        $campus = $this->getUser()->getCampus();

        $sorties = $sortieRepository->findSortiesParCampus($campus);

        $campusList = $campusRepository->findAll();

        return $this->render('accueil/index.html.twig', [
            'sorties' => $sorties,
            'campusList' => $campusList,
        ]);
    }
}
