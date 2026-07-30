<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Participant;

// Service qui va permettre de récupérer getUser(), ainsi après la page d'authentification, si tous les champs obligatoires sont remplis,
// redirection vers la page d'home, sinon redirection vers la page de création de compte

class VerifInfoUser
{
    public function __construct(private Security $security)
    {
    }

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
