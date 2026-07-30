<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Participant;
use App\Entity\Sortie;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Sortie>
 */
class SortieVoter extends Voter
{
    public const EDIT = 'SORTIE_EDIT';
    public const PUBLISH = 'SORTIE_PUBLISH';
    public const CANCEL = 'SORTIE_CANCEL';

    public function __construct(
        private readonly Security $security
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::PUBLISH, self::CANCEL], true)
            && $subject instanceof Sortie;
    }

    protected function voteOnAttribute(
        string         $attribute,
        mixed          $subject,
        TokenInterface $token,
        ?Vote          $vote = null
    ): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Participant) {
            return false;
        }

        /** @var Sortie $sortie */
        $sortie = $subject;

        if (self::CANCEL === $attribute && $this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $isOrganizer = $sortie->getOrganisateur()?->getId() === $user->getId();

        return match ($attribute) {
            self::EDIT, self::PUBLISH, self::CANCEL => $isOrganizer,
            default => false,
        };
    }
}
