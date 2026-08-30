<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;

use function in_array;

final class ImpersonationPolicy
{
    public function canImpersonate(User $impersonator, User $target): bool
    {
        if (!$this->isDeveloper($impersonator)) {
            return false;
        }

        if (null !== $impersonator->getId() && $impersonator->getId() === $target->getId()) {
            return false;
        }

        if (!$this->hasAdminOrOperatorRole($target)) {
            return false;
        }

        if (in_array(UserRole::Developer, $target->getApplicationRoles(), true)) {
            return false;
        }

        if (UserStatus::Inactive === $target->getStatus() || UserStatus::Banned === $target->getStatus()) {
            return false;
        }

        if (!$target->hasBackendAccess()) {
            return false;
        }

        return true;
    }

    private function isDeveloper(User $user): bool
    {
        return in_array(UserRole::Developer, $user->getApplicationRoles(), true);
    }

    private function hasAdminOrOperatorRole(User $user): bool
    {
        $roles = $user->getApplicationRoles();

        return in_array(UserRole::Admin, $roles, true) || in_array(UserRole::Operator, $roles, true);
    }
}
