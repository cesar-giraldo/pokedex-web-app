<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testMapsApplicationRolesToSymfonyRoles(): void
    {
        $user = new User()
            ->setApplicationRoles([UserRole::Developer]);

        self::assertSame(['ROLE_DEVELOPER'], $user->getRoles());
        self::assertTrue($user->hasBackendAccess());
    }

    public function testLocksAccountAfterFourthFailedAttempt(): void
    {
        $user = new User();

        $user->recordFailedLoginAttempt();
        $user->recordFailedLoginAttempt();
        $user->recordFailedLoginAttempt();
        self::assertNull($user->getNoLoginUntil());

        $user->recordFailedLoginAttempt();
        self::assertNotNull($user->getNoLoginUntil());
        self::assertTrue($user->isLoginTemporarilyBlocked());
        self::assertSame(0, $user->getFailedLoginAttempts());
    }

    public function testResetsFailedAttemptsOnSuccessfulLogin(): void
    {
        $user = new User()
            ->recordFailedLoginAttempt()
            ->recordFailedLoginAttempt()
            ->recordFailedLoginAttempt()
            ->recordFailedLoginAttempt();
        self::assertTrue($user->isLoginTemporarilyBlocked());

        $user->resetFailedLoginAttempts();

        self::assertSame(0, $user->getFailedLoginAttempts());
        self::assertNull($user->getNoLoginUntil());
        self::assertFalse($user->isLoginTemporarilyBlocked());
    }

    public function testStatusMessagesAreDefinedInSpanish(): void
    {
        self::assertSame(
            'Debes confirmar tu cuenta antes de iniciar sesión.',
            UserStatus::UnconfirmedAccount->loginDeniedMessage(),
        );
    }

    public function testNormalizesEmailToLowerCase(): void
    {
        $user = new User()->setEmail('Test@Mail.COM');

        self::assertSame('test@mail.com', $user->getEmail());
    }

    public function testNormalizesNicknameToLowerCase(): void
    {
        $user = new User()->setNickname('  Admin-Login  ');

        self::assertSame('admin-login', $user->getNickname());
    }
}
