<?php

declare(strict_types=1);

namespace App\Admin\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

use function in_array;

/**
 * Evaluates role checks against the impersonated user's roles when switch-user is active,
 * excluding ROLE_PREVIOUS_ADMIN so UI reflects only the target account's permissions.
 */
final class EffectiveRoleChecker
{
    private const string PREVIOUS_ADMIN_ROLE = 'ROLE_PREVIOUS_ADMIN';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function isGranted(string $role): bool
    {
        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            return false;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $roles = array_values(array_filter(
            $user->getRoles(),
            static fn (string $assignedRole): bool => self::PREVIOUS_ADMIN_ROLE !== $assignedRole,
        ));

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($roles);

        return in_array($role, $reachableRoles, true);
    }

    public function isImpersonating(): bool
    {
        return $this->tokenStorage->getToken() instanceof SwitchUserToken;
    }
}
