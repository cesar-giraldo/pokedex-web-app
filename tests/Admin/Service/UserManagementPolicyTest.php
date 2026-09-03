<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\UserManagementPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[Group('unit')]
final class UserManagementPolicyTest extends TestCase
{
    private UserManagementPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new UserManagementPolicy();
    }

    public function testDeveloperCanAssignAllRoles(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer]);

        self::assertSame(
            [
                UserRole::Developer,
                UserRole::Admin,
                UserRole::Operator,
                UserRole::User,
            ],
            $this->policy->getAssignableRoles($developer),
        );

        self::assertTrue($this->policy->canAssignRoles($developer, [
            UserRole::Developer,
            UserRole::Admin,
            UserRole::Operator,
            UserRole::User,
        ]));
    }

    public function testAdminCanOnlyAssignOperatorAndUser(): void
    {
        $admin = $this->createUser(2, [UserRole::Admin]);

        self::assertSame(
            [UserRole::Operator, UserRole::User],
            $this->policy->getAssignableRoles($admin),
        );
        self::assertSame([UserRole::Operator], $this->policy->getDefaultRoles($admin));
        self::assertTrue($this->policy->canAssignRoles($admin, [UserRole::Operator, UserRole::User]));
        self::assertFalse($this->policy->canAssignRoles($admin, [UserRole::Admin]));
        self::assertFalse($this->policy->canAssignRoles($admin, [UserRole::Developer]));
    }

    public function testDeveloperCanEditOtherDeveloper(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer]);
        $otherDeveloper = $this->createUser(2, [UserRole::Developer]);

        self::assertTrue($this->policy->canEdit($developer, $otherDeveloper));
    }

    public function testUserCannotEditSelf(): void
    {
        $admin = $this->createUser(3, [UserRole::Admin]);

        self::assertFalse($this->policy->canEdit($admin, $admin));
    }

    public function testAdminCanEditOperatorAndUserButNotAdminOrDeveloper(): void
    {
        $admin = $this->createUser(10, [UserRole::Admin]);
        $operator = $this->createUser(11, [UserRole::Operator]);
        $backendUser = $this->createUser(12, [UserRole::Operator, UserRole::User]);
        $otherAdmin = $this->createUser(13, [UserRole::Admin]);
        $developer = $this->createUser(14, [UserRole::Developer]);

        self::assertTrue($this->policy->canEdit($admin, $operator));
        self::assertTrue($this->policy->canEdit($admin, $backendUser));
        self::assertFalse($this->policy->canEdit($admin, $otherAdmin));
        self::assertFalse($this->policy->canEdit($admin, $developer));
    }

    public function testAdminCannotEditHiddenUsers(): void
    {
        $admin = $this->createUser(20, [UserRole::Admin]);
        $hiddenOperator = $this->createUser(21, [UserRole::Operator]);
        $hiddenOperator->setIsHidden(true);

        self::assertFalse($this->policy->canEdit($admin, $hiddenOperator));
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
