<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Doctrine\UserLastUpdatedAtPolicy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UserLastUpdatedAtPolicyTest extends TestCase
{
    private UserLastUpdatedAtPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new UserLastUpdatedAtPolicy();
    }

    public function testDoesNotTouchForLoginTrackingFields(): void
    {
        self::assertFalse($this->policy->shouldTouch([
            'lastLoginAt',
            'lastLoginIp',
            'failedLoginAttempts',
            'noLoginUntil',
        ]));
    }

    public function testDoesNotTouchForFailedLoginTracking(): void
    {
        self::assertFalse($this->policy->shouldTouch([
            'lastFailedLoginAt',
            'failedLoginAttempts',
        ]));
    }

    public function testDoesNotTouchForPasswordChange(): void
    {
        self::assertFalse($this->policy->shouldTouch([
            'password',
            'passwordUpdatedAt',
        ]));
    }

    public function testTouchesWhenProfileDataChanges(): void
    {
        self::assertTrue($this->policy->shouldTouch(['name']));
        self::assertTrue($this->policy->shouldTouch(['email', 'cellphone']));
        self::assertTrue($this->policy->shouldTouch(['profileImagePath']));
        self::assertTrue($this->policy->shouldTouch(['status', 'roles']));
    }

    public function testTouchesWhenProfileDataChangesAlongsidePassword(): void
    {
        self::assertTrue($this->policy->shouldTouch([
            'name',
            'password',
            'passwordUpdatedAt',
        ]));
    }

    public function testDoesNotTouchWhenChangeSetIsEmpty(): void
    {
        self::assertFalse($this->policy->shouldTouch([]));
    }
}
