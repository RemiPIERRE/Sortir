<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Participant;
use App\Entity\Sortie;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SortieVoter extends Voter
{
    public const MODIFIER = 'SORTIE_MODIFIER';
    public const PUBLIER = 'SORTIE_PUBLIER';
    public const ANNULER = 'SORTIE_ANNULER';

    public function __construct(
        private readonly Security $security
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::MODIFIER, self::PUBLIER, self::ANNULER], true)
            && $subject instanceof Sortie;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Participant) {
            return false;
        }

        $sortie = $subject;

        if (self::ANNULER === $attribute && $this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $estOrganisateur = $sortie->getOrganisateur()?->getId() === $user->getId();

        return match ($attribute) {
            self::MODIFIER, self::PUBLIER, self::ANNULER => $estOrganisateur,
            default => false,
        };
    }
}
