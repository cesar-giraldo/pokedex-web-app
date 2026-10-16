<?php

declare(strict_types=1);

namespace App\Tests\Admin\Security;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Security\AccountStatusUserChecker;
use DateTime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

#[Group('unit')]
final class UserCheckerTest extends TestCase
{
    private AccountStatusUserChecker $userChecker;

    protected function setUp(): void
    {
        $this->userChecker = new AccountStatusUserChecker();
    }

    public function testBlocksUnconfirmedAccount(): void
    {
        $user = $this->createUser(UserStatus::UnconfirmedAccount);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('account_unconfirmed');

        $this->userChecker->checkPreAuth($user);
    }

    public function testBlocksTemporaryLoginLock(): void
    {
        $user = $this->createUser(UserStatus::Active);
        $user->setNoLoginUntil(new DateTime('+30 minutes'));

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('login_temporarily_blocked_other');

        $this->userChecker->checkPreAuth($user);
    }

    public function testImpersonationSkipsTemporaryLoginLock(): void
    {
        $user = $this->createUser(UserStatus::Active);
        $user->setNoLoginUntil(new DateTime('+30 minutes'));
        $tokenStorage = new TokenStorage();
        $originalToken = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken(new SwitchUserToken($user, 'public', $user->getRoles(), $originalToken));
        $checker = new AccountStatusUserChecker($tokenStorage);

        $checker->checkPreAuth($user);

        self::assertTrue($user->isLoginTemporarilyBlocked());
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
