<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Security\EffectiveRoleChecker;
use App\Admin\Service\Storage\UserProfileImageAccessPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

final class UserProfileImageAccessPolicyTest extends TestCase
{
    public function testOperatorCanViewVisibleUser(): void
    {
        $viewer = $this->createUser(1, [UserRole::Operator]);
        $target = $this->createUser(2, [UserRole::Operator]);
        $policy = new UserProfileImageAccessPolicy($this->createRoleChecker($viewer));

        self::assertTrue($policy->canView($viewer, $target));
    }

    public function testOperatorCannotViewHiddenUser(): void
    {
        $viewer = $this->createUser(1, [UserRole::Operator]);
        $target = $this->createUser(2, [UserRole::Operator]);
        $target->setIsHidden(true);
        $policy = new UserProfileImageAccessPolicy($this->createRoleChecker($viewer));

        self::assertFalse($policy->canView($viewer, $target));
    }

    public function testDeveloperCanViewHiddenUser(): void
    {
        $viewer = $this->createUser(1, [UserRole::Developer]);
        $target = $this->createUser(2, [UserRole::Operator]);
        $target->setIsHidden(true);
        $policy = new UserProfileImageAccessPolicy($this->createRoleChecker($viewer));

        self::assertTrue($policy->canView($viewer, $target));
    }

    private function createRoleChecker(User $user): EffectiveRoleChecker
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return new EffectiveRoleChecker($tokenStorage, new RoleHierarchy([]));
    }

    /**
     * @param list<UserRole> $roles
     */
    private function createUser(int $id, array $roles): User
    {
        $user = new User();
        $user->setName('Test');
        $user->setLastname('User');
        $user->setNickname('user-' . $id);
        $user->setApplicationRoles($roles);

        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
