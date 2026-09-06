<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Entity\Enum\UserRole;
use App\Entity\User;

use function in_array;

final class UserManagementPolicy
{
    /**
     * @return list<UserRole>
     */
    public function getAssignableRoles(User $editor): array
    {
        if ($this->isDeveloper($editor)) {
            return [
                UserRole::Developer,
                UserRole::Admin,
                UserRole::Operator,
                UserRole::User,
            ];
        }

        return [
            UserRole::Operator,
            UserRole::User,
        ];
    }

    /**
     * @return list<UserRole>
     */
    public function getDefaultRoles(User $editor): array
    {
        if ($this->isDeveloper($editor)) {
            return [];
        }

        return [UserRole::Operator];
    }

    /**
     * @param list<UserRole> $roles
     */
    public function canAssignRoles(User $editor, array $roles): bool
    {
        if ([] === $roles) {
            return false;
        }

        $assignable = $this->getAssignableRoles($editor);

        foreach ($roles as $role) {
            if (!in_array($role, $assignable, true)) {
                return false;
            }
        }

        return true;
    }

    public function canEdit(User $editor, User $target): bool
    {
        if (null !== $editor->getId() && $editor->getId() === $target->getId()) {
            return false;
        }

        if ($this->isDeveloper($editor)) {
            return true;
        }

        if (true === $target->isHidden()) {
            return false;
        }

        return $this->hasOnlyRoles($target, [UserRole::Operator, UserRole::User]);
    }

    public function canView(User $viewer, User $target): bool
    {
        if (true !== $target->isHidden()) {
            return true;
        }

        return $this->isDeveloper($viewer);
    }

    /**
     * @param list<UserRole> $allowedRoles
     */
    private function hasOnlyRoles(User $user, array $allowedRoles): bool
    {
        $roles = $user->getApplicationRoles();

        if ([] === $roles) {
            return false;
        }

        foreach ($roles as $role) {
            if (!in_array($role, $allowedRoles, true)) {
                return false;
            }
        }

        return true;
    }

    private function isDeveloper(User $user): bool
    {
        return in_array(UserRole::Developer, $user->getApplicationRoles(), true);
    }
}
