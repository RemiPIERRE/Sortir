<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Aiguillage post-connexion : redirige vers le back-office ou l'accueil selon le rôle.
 */
#[IsGranted('ROLE_USER')]
class RedirectAfterLoginController extends AbstractController
{
    /**
     * Redirige un administrateur vers le tableau de bord, tout autre utilisateur
     * vers la page d'accueil.
     */
    #[Route('/redirection-connexion', name: 'app_redirect_after_login', methods: ['GET'])]
    public function redirectAfterLogin(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_home');
        }

        return $this->redirectToRoute('app_home');
    }
}
