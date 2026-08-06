<?php

namespace App\Security;

use App\Entity\Participant;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Vérifie l'éligibilité d'un compte au moment de l'authentification.
 *
 * Bloque la connexion des comptes désactivés (actif = false).
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Rejette l'authentification d'un compte désactivé, avant vérification des identifiants.
     *
     * @throws CustomUserMessageAccountStatusException si le compte est inactif
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Participant) {
            return;
        }

        if (!$user->isActif()) {
            throw new CustomUserMessageAccountStatusException('Votre compte est désactivé. Contactez un administrateur.');
        }
    }

    /**
     * Aucune vérification post-authentification n'est nécessaire ici.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
