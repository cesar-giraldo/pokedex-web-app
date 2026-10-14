<?php

declare(strict_types=1);

namespace App\Web\Security;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class PublicAccountVoter extends Voter
{
    public const string ATTRIBUTE = 'PUBLIC_ACCOUNT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return false;
        }

        return match ($user->getStatus()) {
            UserStatus::Active, UserStatus::UncompleteProfileInfo => true,
            default => false,
        };
    }
}
