<?php

declare(strict_types=1);

namespace App\Tests\Admin\EventSubscriber;

use App\Admin\EventSubscriber\AdminSecuritySubscriber;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class AdminSecuritySubscriberTest extends TestCase
{
    private UserRepository&MockObject $userRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private AdminSecuritySubscriber $subscriber;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = new AdminSecuritySubscriber(
            $this->userRepository,
            $this->entityManager,
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
        );
    }

    public function testDoesNotIncrementAttemptsForUnknownNickname(): void
    {
        $request = $this->createLoginRequest('unknown-user');
        $event = new LoginFailureEvent(
            new BadCredentialsException(),
            $this->createMock(AuthenticatorInterface::class),
            $request,
            null,
            'main',
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneByNickname')
            ->with('unknown-user')
            ->willReturn(null);

        $this->entityManager->expects($this->never())->method('flush');

        $this->subscriber->onLoginFailure($event);
    }

    public function testIncrementsAttemptsOnlyForBadCredentials(): void
    {
        $user = $this->createActiveUser();
        $request = $this->createLoginRequest($user->getNickname());
        $event = new LoginFailureEvent(
            new BadCredentialsException(),
            $this->createMock(AuthenticatorInterface::class),
            $request,
            null,
            'main',
        );

        $this->userRepository
            ->method('findOneByNickname')
            ->willReturn($user);

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onLoginFailure($event);

        self::assertSame(1, $user->getFailedLoginAttempts());
    }

    public function testDoesNotIncrementAttemptsWhenAccountIsTemporarilyLocked(): void
    {
        $user = $this->createActiveUser();
        $user->setNoLoginUntil(new DateTime('+2 hours'));

        $request = $this->createLoginRequest($user->getNickname());
        $event = new LoginFailureEvent(
            new CustomUserMessageAccountStatusException($user->loginTemporaryLockMessage()),
            $this->createMock(AuthenticatorInterface::class),
            $request,
            null,
            'main',
        );

        $this->userRepository
            ->method('findOneByNickname')
            ->willReturn($user);

        $this->entityManager->expects($this->never())->method('flush');

        $this->subscriber->onLoginFailure($event);

        self::assertSame(0, $user->getFailedLoginAttempts());
    }

    public function testDoesNotIncrementAttemptsForAccountStatusFailures(): void
    {
        $user = $this->createActiveUser();
        $user->setStatus(UserStatus::UnconfirmedAccount);

        $request = $this->createLoginRequest($user->getNickname());
        $event = new LoginFailureEvent(
            new CustomUserMessageAccountStatusException(UserStatus::UnconfirmedAccount->loginDeniedMessage()),
            $this->createMock(AuthenticatorInterface::class),
            $request,
            null,
            'main',
        );

        $this->userRepository
            ->method('findOneByNickname')
            ->willReturn($user);

        $this->entityManager->expects($this->never())->method('flush');

        $this->subscriber->onLoginFailure($event);

        self::assertSame(0, $user->getFailedLoginAttempts());
    }

    public function testResetsAttemptsAndLockOnSuccessfulLogin(): void
    {
        $user = $this->createActiveUser();
        $user->recordFailedLoginAttempt();
        $user->recordFailedLoginAttempt();

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/admin/pokemons');

        $subscriber = new AdminSecuritySubscriber(
            $this->userRepository,
            $this->entityManager,
            $this->createMock(TokenStorageInterface::class),
            $urlGenerator,
        );

        $request = Request::create('/admin/login', 'POST');
        $request->attributes->set('_route', 'app_admin_login');

        $passport = new SelfValidatingPassport(new UserBadge(
            $user->getUserIdentifier(),
            static fn (): User => $user,
        ));

        $event = new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            $passport,
            $this->createMock(TokenInterface::class),
            $request,
            null,
            'main',
        );

        $this->entityManager->expects($this->once())->method('flush');

        $subscriber->onLoginSuccess($event);

        self::assertSame(0, $user->getFailedLoginAttempts());
        self::assertNull($user->getNoLoginUntil());
    }

    private function createLoginRequest(string $nickname): Request
    {
        $request = Request::create('/admin/login', 'POST', [
            '_username' => $nickname,
        ]);
        $request->attributes->set('_route', 'app_admin_login');

        return $request;
    }

    private function createActiveUser(): User
    {
        return new User()
            ->setName('Test')
            ->setLastname('User')
            ->setEmail('test@example.com')
            ->setNickname('test-user')
            ->setPassword('hashed')
            ->setApplicationRoles([UserRole::Admin])
            ->setStatus(UserStatus::Active);
    }
}
