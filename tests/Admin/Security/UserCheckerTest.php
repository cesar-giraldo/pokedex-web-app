<?php

declare(strict_types=1);

namespace App\Tests\Admin\Security;

use App\Admin\Security\UserChecker;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use DateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserCheckerTest extends TestCase
{
    private UserChecker $userChecker;

    protected function setUp(): void
    {
        $this->userChecker = new UserChecker();
    }

    public function testBlocksUnconfirmedAccount(): void
    {
        $user = $this->createUser(UserStatus::UnconfirmedAccount);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Debes confirmar tu cuenta antes de iniciar sesión.');

        $this->userChecker->checkPreAuth($user);
    }

    public function testBlocksTemporaryLoginLock(): void
    {
        $user = $this->createUser(UserStatus::Active);
        $user->setNoLoginUntil(new DateTime('+2 hours'));

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Has superado el número de intentos permitidos.');

        $this->userChecker->checkPreAuth($user);
    }

    public function testAllowsIncompleteProfileLogin(): void
    {
        $user = $this->createUser(UserStatus::UncompleteProfileInfo);

        $this->userChecker->checkPreAuth($user);
        $this->userChecker->checkPostAuth($user);

        self::assertSame(UserStatus::UncompleteProfileInfo, $user->getStatus());
    }

    private function createUser(UserStatus $status): User
    {
        return new User()
            ->setName('Test')
            ->setLastname('User')
            ->setEmail('test@example.com')
            ->setNickname('testuser')
            ->setPassword('hashed')
            ->setApplicationRoles([UserRole::Admin])
            ->setStatus($status);
    }
}
