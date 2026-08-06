<?php

namespace App\Controller;

use App\Repository\CampusRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Authentification : affichage du formulaire de connexion et point de déconnexion.
 */
class SecurityController extends AbstractController
{
    /**
     * Affiche la page de connexion avec quelques statistiques publiques
     * (participants actifs, sorties, campus) et l'éventuelle erreur d'authentification.
     */
    #[Route(path: '/login', name: 'app_login')]
    public function login(
        AuthenticationUtils   $authenticationUtils,
        ParticipantRepository $participantRepository,
        SortieRepository      $sortieRepository,
        CampusRepository      $campusRepository
    ): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'nbParticipants' => $participantRepository->count(['actif' => true]),
            'nbSorties' => $sortieRepository->count([]),
            'nbCampus' => $campusRepository->count([]),
        ]);
    }

    /**
     * Point de déconnexion. Le corps reste vide : la déconnexion est interceptée
     * par le firewall Symfony (clé « logout »).
     *
     * @throws \LogicException jamais réellement levée (méthode interceptée)
     */
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
