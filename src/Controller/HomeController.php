<?php

namespace App\Controller;

use App\Entity\Sortie;
use App\Repository\SortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_sortie_list', methods: ['GET'])]
    public function list(SortieRepository $sortieRepository): Response
    {
        return $this->render('sortie/temp_list.html.twig', [
            'sorties' => $sortieRepository->findBy([], ['dateHeureDebut' => 'ASC']),
        ]);
    }
}
