<?php

declare(strict_types=1);

namespace App\Admin\Security;

use App\Entity\User;
use App\Entity\Enum\UserStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isLoginTemporarilyBlocked()) {
            throw new CustomUserMessageAccountStatusException($user->loginTemporaryLockMessage());
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

        if ($user->getStatus() === UserStatus::UncompleteProfileInfo) {
            return;
        }

        if ($user->getStatus() !== UserStatus::Active) {
            throw new CustomUserMessageAccountStatusException($user->getStatus()->loginDeniedMessage());
        }
    }
}
