<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Enum\UserStatus;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AccountStatusUserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isLoginTemporarilyBlocked() && !$this->isImpersonated($user)) {
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

    private function isImpersonated(User $user): bool
    {
        $token = $this->tokenStorage?->getToken();

        if (!$token instanceof SwitchUserToken) {
            return false;
        }

        $impersonatedUser = $token->getUser();

        return $impersonatedUser instanceof User
            && $impersonatedUser->getUserIdentifier() === $user->getUserIdentifier();
    }
}
