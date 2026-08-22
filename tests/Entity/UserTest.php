<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use DateTime;
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

        $noLoginUntil = $user->getNoLoginUntil();
        $remainingSeconds = $noLoginUntil->getTimestamp() - time();
        self::assertGreaterThan(User::LOGIN_LOCK_MINUTES * 60 - 5, $remainingSeconds);
        self::assertLessThanOrEqual(User::LOGIN_LOCK_MINUTES * 60, $remainingSeconds);
    }

    public function testLoginTemporaryLockMessageShowsRemainingMinutes(): void
    {
        $user = new User();
        $user->setNoLoginUntil(new DateTime('+45 minutes'));

        self::assertSame(45, $user->getRemainingLoginLockMinutes());
        self::assertSame(
            'Has superado el número de intentos permitidos. Debes esperar 45 minutos para volver a intentarlo.',
            $user->loginTemporaryLockMessage(),
        );

        $user->setNoLoginUntil(new DateTime('+1 minute'));

        self::assertSame(1, $user->getRemainingLoginLockMinutes());
        self::assertSame(
            'Has superado el número de intentos permitidos. Debes esperar 1 minuto para volver a intentarlo.',
            $user->loginTemporaryLockMessage(),
        );
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

    public function testPasswordUpdatedAtMatchesCreatedAtOnConstruction(): void
    {
        $user = new User();

        self::assertSame(
            $user->getCreatedAt()->format('Y-m-d H:i:s'),
            $user->getPasswordUpdatedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testDetectsCompleteProfileContactInfo(): void
    {
        $user = new User()
            ->setEmail('complete@example.com')
            ->setCountryCode(57)
            ->setCellphone('3001234567');

        self::assertTrue($user->hasCompleteProfileContactInfo());
    }

    public function testFormatsPhoneWithCountryCode(): void
    {
        $user = new User()
            ->setCountryCode(57)
            ->setCellphone('3001234567');

        self::assertSame('+57 3001234567', $user->getFormattedPhone());
    }

    public function testReturnsPrimaryApplicationRoleByHierarchy(): void
    {
        $user = new User()
            ->setApplicationRoles([UserRole::Operator, UserRole::Admin]);

        self::assertSame(UserRole::Admin, $user->getPrimaryApplicationRole());
    }
}
