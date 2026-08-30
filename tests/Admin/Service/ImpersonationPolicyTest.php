<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\ImpersonationPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ImpersonationPolicyTest extends TestCase
{
    private ImpersonationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ImpersonationPolicy();
    }

    public function testDeveloperCanImpersonateAdminAndOperator(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer], UserStatus::Active);
        $admin = $this->createUser(2, [UserRole::Admin], UserStatus::Active);
        $operator = $this->createUser(3, [UserRole::Operator], UserStatus::Active);

        self::assertTrue($this->policy->canImpersonate($developer, $admin));
        self::assertTrue($this->policy->canImpersonate($developer, $operator));
    }

    public function testDeveloperCannotImpersonateDeveloper(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer], UserStatus::Active);
        $otherDeveloper = $this->createUser(2, [UserRole::Developer], UserStatus::Active);

        self::assertFalse($this->policy->canImpersonate($developer, $otherDeveloper));
    }

    public function testDeveloperCannotImpersonateUserRoleOnly(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer], UserStatus::Active);
        $frontendUser = $this->createUser(2, [UserRole::User], UserStatus::Active);

        self::assertFalse($this->policy->canImpersonate($developer, $frontendUser));
    }

    public function testDeveloperCannotImpersonateInactiveOrBannedUsers(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer], UserStatus::Active);
        $inactiveOperator = $this->createUser(2, [UserRole::Operator], UserStatus::Inactive);
        $bannedAdmin = $this->createUser(3, [UserRole::Admin], UserStatus::Banned);

        self::assertFalse($this->policy->canImpersonate($developer, $inactiveOperator));
        self::assertFalse($this->policy->canImpersonate($developer, $bannedAdmin));
    }

    public function testDeveloperCannotImpersonateSelf(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer], UserStatus::Active);

        self::assertFalse($this->policy->canImpersonate($developer, $developer));
    }

    public function testNonDeveloperCannotImpersonate(): void
    {
        $admin = $this->createUser(1, [UserRole::Admin], UserStatus::Active);
        $operator = $this->createUser(2, [UserRole::Operator], UserStatus::Active);

        self::assertFalse($this->policy->canImpersonate($admin, $operator));
    }

    /**
     * @param list<UserRole> $roles
     */
    private function createUser(int $id, array $roles, UserStatus $status): User
    {
        $user = new User();
        $user->setName('Test');
        $user->setLastname('User');
        $user->setNickname('user-' . $id);
        $user->setApplicationRoles($roles);
        $user->setStatus($status);

        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
