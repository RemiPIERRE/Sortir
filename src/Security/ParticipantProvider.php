<?php

namespace App\Security;

use App\Entity\Participant;
use App\Repository\ParticipantRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Provider personnalisé pour charger un Participant depuis la base de données.
 *
 * Pourquoi ce fichier existe :
 * Le provider standard de Symfony (celui configuré directement en YAML)
 * ne sait chercher un utilisateur QUE sur un seul champ (ex: juste "email").
 * Ici, on veut permettre à l'utilisateur de se connecter avec son email
 * OU son pseudo (comme demandé dans la maquette "Identifiant").
 * Ce provider personnalisé nous permet d'écrire nous-mêmes cette requête.
 */
class ParticipantProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    // Symfony va automatiquement injecter le repository de Participant ici
    // grâce à l'injection de dépendances (pas besoin de faire "new" nous-mêmes)
    public function __construct(private ParticipantRepository $repository)
    {
    }

    /**
     * Cette méthode est appelée automatiquement par Symfony au moment du login,
     * avec ce que l'utilisateur a tapé dans le champ "Identifiant" du formulaire.
     *
     * @param string $identifier Ce que l'utilisateur a saisi (email OU pseudo)
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // On construit une requête Doctrine qui cherche un Participant
        // dont l'email correspond OU dont le pseudo correspond à ce qui a été saisi
        $user = $this->repository->createQueryBuilder('p')
            ->where('p.email = :identifier OR p.pseudo = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult(); // Renvoie null si rien n'est trouvé (au lieu de planter)

        // Si aucun utilisateur ne correspond, on lève une exception spécifique
        // que Symfony sait reconnaître pour afficher "Identifiants invalides"
        if (!$user) {
            throw new UserNotFoundException(sprintf("Aucun compte trouvé pour '%s'.", $identifier));
        }

        return $user;
    }

    /**
     * Cette méthode est appelée par Symfony pour "rafraîchir" les infos de l'utilisateur
     * à chaque requête (par exemple, si son rôle a changé en base depuis sa connexion).
     * Elle recharge simplement l'utilisateur depuis la base via son identifiant.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        // Sécurité : on vérifie qu'on manipule bien un Participant,
        // et pas un autre type d'utilisateur (au cas où il y en aurait plusieurs types un jour)
        if (!$user instanceof Participant) {
            throw new \InvalidArgumentException('Instance de Participant attendue.');
        }

        // On recharge l'utilisateur frais depuis la base
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Cette méthode dit à Symfony : "je suis capable de gérer ce type de classe".
     * Elle est appelée en interne par Symfony pour vérifier la compatibilité
     * entre ce provider et l'entité Participant.
     */
    public function supportsClass(string $class): bool
    {
        return $class === Participant::class || is_subclass_of($class, Participant::class);
    }

    /**
     * Cette méthode est appelée automatiquement par Symfony quand l'algorithme
     * de hashage du mot de passe a changé (mise à jour de sécurité), pour
     * re-hasher et sauvegarder le mot de passe avec le nouvel algorithme.
     * On n'a pas besoin de l'appeler nous-mêmes, Symfony s'en charge tout seul.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
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
