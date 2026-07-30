<?php

namespace App\Controller;

use App\Repository\SortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SortieController extends AbstractController
{
    #[Route('/sortie/{id}', name: 'sortie_afficherSortie', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function afficherSortie(int $id, SortieRepository $sortieRepository): Response
    {
        $sortie = $sortieRepository->find($id);

        if (!$sortie) {
            throw $this->createNotFoundException("Cette sortie n'existe pas.");
        }

        return $this->render('sortie/afficherSortie.html.twig', [
            'sortie' => $sortie,
        ]);
    }
}
