<?php

namespace App\Security;

use App\Entity\Participant;
use App\Repository\ParticipantRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

// service qui permet d'ajouter un identifiant à Participant
// security.yaml ne permet d'ajouter email ET pseudo, d'où ce fichier


/**
 * Fournisseur d'utilisateurs pour le firewall.
 *
 * Permet de s'authentifier indifféremment par email OU par pseudo — ce que la
 * configuration standard de security.yaml ne permet pas — et prend en charge la
 * mise à niveau transparente du hash de mot de passe.
 */
class ParticipantProvider implements UserProviderInterface, PasswordUpgraderInterface
{

    public function __construct(private ParticipantRepository $repository) // on injecte ParticipantRepository
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface // méthode appelée par Symfony au moment du login / $identifier : ce que l'utilisateur a saisi
    {
        //loadUserByIdentifer est fourni par UserProviderInterface

        $user = $this->repository->createQueryBuilder('p') // requête Doctrine qui cherche un participant en fonction de l'identifier
            ->where('p.email = :identifier OR p.pseudo = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult(); // Renvoie null si rien n'est trouvé sinon ça plante

        // Si aucun utilisateur ne correspond, on lève une exception spécifiquen que Symfony sait reconnaître pour afficher "Identifiants invalides"
        if (!$user) {
            throw new UserNotFoundException(sprintf("Aucun compte trouvé pour '%s'.", $identifier));
        }

        return $user;
    }


    public function refreshUser(UserInterface $user): UserInterface // recharge l'utilisateur depuis la base via son identifiant
    {
        // Sécurité : on vérifie qu'on manipule bien un Participant, et pas un autre type d'utilisateur (au cas où il y en aurait plusieurs types un jour)
        if (!$user instanceof Participant) {
            throw new \InvalidArgumentException('Instance de Participant attendue.');
        }

        // On recharge l'utilisateur frais depuis la base
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    // méthode qui dit "je gère ce type de classe"
     // elle est appelée en interne par Symfony pour vérifier la compatibilité entre ce provider et l'entité Participant

    public function supportsClass(string $class): bool
    {
        return $class === Participant::class || is_subclass_of($class, Participant::class);
    }


    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void // met à jour le mdp stocké si l'algo de sécurité utilisé a changé
    {
        // Sécurité : on vérifie qu'on manipule bien un Participant
        if (!$user instanceof Participant) {
            return;
        }

        // On met à jour le mot de passe avec la nouvelle version hashée
        $user->setPassword($newHashedPassword);

        // Note : ici, il manque le flush() en base pour que ce soit persisté.
        // On pourra l'ajouter plus tard si besoin (avec l'EntityManager injecté).
    }
}
