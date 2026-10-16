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

    public function canImpersonateOnWeb(User $impersonator, User $target): bool
    {
        if (!$this->isAdminOrDeveloper($impersonator)) {
            return false;
        }

        if (null !== $impersonator->getId() && $impersonator->getId() === $target->getId()) {
            return false;
        }

        if (!$target->hasAssignedRole(UserRole::User)) {
            return false;
        }

        if (!in_array($target->getStatus(), [UserStatus::Active, UserStatus::UncompleteProfileInfo], true)) {
            return false;
        }

        $targetRole = UserRole::primaryFromRoles($target->getApplicationRoles());
        $impersonatorRole = UserRole::primaryFromRoles($impersonator->getApplicationRoles());

        if (!$targetRole instanceof UserRole || !$impersonatorRole instanceof UserRole) {
            return false;
        }

        if (UserRole::Developer === $targetRole) {
            return false;
        }

        return $targetRole->hierarchyRank() <= $impersonatorRole->hierarchyRank();
    }

    private function isDeveloper(User $user): bool
    {
        return in_array(UserRole::Developer, $user->getApplicationRoles(), true);
    }

    private function isAdminOrDeveloper(User $user): bool
    {
        $roles = $user->getApplicationRoles();

        return in_array(UserRole::Developer, $roles, true) || in_array(UserRole::Admin, $roles, true);
    }

    private function hasAdminOrOperatorRole(User $user): bool
    {
        $roles = $user->getApplicationRoles();

        return in_array(UserRole::Admin, $roles, true) || in_array(UserRole::Operator, $roles, true);
    }
}
