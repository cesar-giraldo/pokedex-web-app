<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\ImpersonationPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use DateTime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[Group('unit')]
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

    public function testAdminAndDeveloperCanImpersonatePublicUsersOnWeb(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer], UserStatus::Active);
        $admin = $this->createUser(2, [UserRole::Admin], UserStatus::Active);
        $operator = $this->createUser(3, [UserRole::Operator], UserStatus::Active);
        $publicUser = $this->createUser(4, [UserRole::User], UserStatus::Active);
        $incompleteUser = $this->createUser(5, [UserRole::User], UserStatus::UncompleteProfileInfo);
        $adminWithPublicRole = $this->createUser(6, [UserRole::Admin, UserRole::User], UserStatus::Active);

        self::assertTrue($this->policy->canImpersonateOnWeb($developer, $publicUser));
        self::assertTrue($this->policy->canImpersonateOnWeb($admin, $publicUser));
        self::assertTrue($this->policy->canImpersonateOnWeb($developer, $incompleteUser));
        self::assertTrue($this->policy->canImpersonateOnWeb($admin, $incompleteUser));
        self::assertTrue($this->policy->canImpersonateOnWeb($developer, $adminWithPublicRole));
        self::assertTrue($this->policy->canImpersonateOnWeb($admin, $adminWithPublicRole));
        self::assertFalse($this->policy->canImpersonateOnWeb($operator, $publicUser));
    }

    public function testWebImpersonationRejectsHigherRolesAndBlockedStatuses(): void
    {
        $developer = $this->createUser(1, [UserRole::Developer, UserRole::User], UserStatus::Active);
        $admin = $this->createUser(2, [UserRole::Admin], UserStatus::Active);
        $otherDeveloper = $this->createUser(3, [UserRole::Developer, UserRole::User], UserStatus::Active);
        $inactiveUser = $this->createUser(4, [UserRole::User], UserStatus::Inactive);
        $bannedUser = $this->createUser(5, [UserRole::User], UserStatus::Banned);
        $unconfirmedUser = $this->createUser(6, [UserRole::User], UserStatus::UnconfirmedAccount);
        $backendOnly = $this->createUser(7, [UserRole::Operator], UserStatus::Active);

        self::assertFalse($this->policy->canImpersonateOnWeb($developer, $otherDeveloper));
        self::assertFalse($this->policy->canImpersonateOnWeb($admin, $otherDeveloper));
        self::assertFalse($this->policy->canImpersonateOnWeb($developer, $developer));
        self::assertFalse($this->policy->canImpersonateOnWeb($admin, $inactiveUser));
        self::assertFalse($this->policy->canImpersonateOnWeb($admin, $bannedUser));
        self::assertFalse($this->policy->canImpersonateOnWeb($admin, $unconfirmedUser));
        self::assertFalse($this->policy->canImpersonateOnWeb($developer, $backendOnly));
    }

    public function testWebImpersonationAllowsTemporarilyLockedPublicAccounts(): void
    {
        $admin = $this->createUser(1, [UserRole::Admin], UserStatus::Active);
        $lockedUser = $this->createUser(2, [UserRole::User], UserStatus::Active);
        $lockedUser->setNoLoginUntil(new DateTime('+30 minutes'));

        self::assertTrue($lockedUser->isLoginTemporarilyBlocked());
        self::assertTrue($this->policy->canImpersonateOnWeb($admin, $lockedUser));
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
