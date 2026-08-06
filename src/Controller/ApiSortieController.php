<?php

namespace App\Controller;

use App\Repository\SortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sorties', name: 'api_sorties_')]
class ApiSortieController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'], format: 'json')]
    public function list(
        Request $request,
        SortieRepository $sortieRepository
    ): JsonResponse {
        $etat = $request->query->get('etat');
        $dateSaisie = $request->query->get('date');

        $date = null;

        if ($dateSaisie) {
            $date = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dateSaisie
            );

            if (!$date) {
                return $this->json(
                    [
                        'erreur' => 'La date doit respecter le format YYYY-MM-DD.',
                    ],
                    400
                );
            }
        }

        $sorties = $sortieRepository->findForApi($etat, $date);

        $resultat = [];

        foreach ($sorties as $sortie) {
            $resultat[] = [
                'id' => $sortie->getId(),
                'nom' => $sortie->getNom(),
                'dateHeureDebut' => $sortie
                    ->getDateHeureDebut()
                    ?->format('Y-m-d H:i:s'),
                'etat' => $sortie
                    ->getEtat()
                    ?->getLibelle(),
            ];
        }

        return $this->json($resultat);
    }
}
