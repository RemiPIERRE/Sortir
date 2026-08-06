<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Participant;

// Service qui va permettre de récupérer getUser(), ainsi après la page d'authentification, si tous les champs obligatoires sont remplis,
// redirection vers la page d'accueil, sinon redirection vers la page de création de compte

/**
 * Détermine si le profil de l'utilisateur courant est complet.
 *
 * Utilisé après connexion pour aiguiller vers la complétion de compte tant que
 * les champs obligatoires (pseudo, nom, prénom) ne sont pas renseignés.
 */
class VerifInfoUser
{
    public function __construct(private Security $security)
    {
    }

    /**
     * Retourne true si pseudo, nom et prénom de l'utilisateur courant sont tous renseignés.
     */
    public function profileIsComplete(): bool
    {
        $user = $this->security->getUser();

        if (!$user instanceof Participant) {
            return false;
        }

        return !empty($user->getPseudo())
            && !empty ($user->getNom())
            && !empty ($user->getPrenom());
    }
}
