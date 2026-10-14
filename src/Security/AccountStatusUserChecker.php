<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Enum\UserStatus;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AccountStatusUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isLoginTemporarilyBlocked()) {
            $minutes = max($user->getRemainingLoginLockMinutes(), 1);

            throw new CustomUserMessageAccountStatusException(
                1 === $minutes ? 'login_temporarily_blocked_one' : 'login_temporarily_blocked_other',
                ['%minutes%' => $minutes],
            );
        }

        if (!$user->getStatus()->allowsBackendLogin()) {
            throw new CustomUserMessageAccountStatusException($user->getStatus()->loginDeniedMessage());
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (UserStatus::UncompleteProfileInfo === $user->getStatus()) {
            return;
        }

        if (UserStatus::Active !== $user->getStatus()) {
            throw new CustomUserMessageAccountStatusException(
                $user->getStatus()->loginDeniedMessage()
            );
        }
    }
}
