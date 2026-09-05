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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[Group('unit')]
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
        self::assertNotNull($user->getLastFailedLoginAt());
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
        self::assertNotNull($user->getLastLoginAt());
        self::assertSame('127.0.0.1', $user->getLastLoginIp());
        self::assertNotNull($user->getLastFailedLoginAt());
    }

    public function testDoesNotRecordLastLoginWhenUserLacksBackendAccess(): void
    {
        $user = $this->createActiveUser();
        $user->setApplicationRoles([UserRole::User]);

        $request = $this->createLoginRequest($user->getNickname());
        $request->setSession(new Session(new MockArraySessionStorage()));

        $subscriber = $this->createSubscriberWithHomeRedirect();
        $event = $this->createLoginSuccessEvent($user, $request);

        $this->entityManager->expects($this->once())->method('flush');

        $subscriber->onLoginSuccess($event);

        self::assertNull($user->getLastLoginAt());
        self::assertNull($user->getLastLoginIp());
    }

    public function testDoesNotRecordLastLoginOnRememberMeAuthentication(): void
    {
        $user = $this->createActiveUser();
        $request = Request::create('/admin/home', 'GET');

        $subscriber = $this->createSubscriberWithHomeRedirect();
        $event = $this->createLoginSuccessEvent($user, $request);

        $this->entityManager->expects($this->once())->method('flush');

        $subscriber->onLoginSuccess($event);

        self::assertNull($user->getLastLoginAt());
        self::assertNull($user->getLastLoginIp());
    }

    public function testDoesNotRecordLastLoginWhenImpersonating(): void
    {
        $developer = $this->createActiveUser()
            ->setNickname('developer-user')
            ->setApplicationRoles([UserRole::Developer]);
        $operator = $this->createActiveUser()
            ->setNickname('operator-user')
            ->setApplicationRoles([UserRole::Operator]);

        $originalToken = new UsernamePasswordToken($developer, 'main', $developer->getRoles());
        $switchToken = new SwitchUserToken($operator, 'main', $operator->getRoles(), $originalToken);

        $request = $this->createLoginRequest($operator->getNickname());
        $request->query->set('_switch_user', $operator->getNickname());

        $subscriber = $this->createSubscriberWithHomeRedirect();
        $event = $this->createLoginSuccessEvent($operator, $request, $switchToken);

        $this->entityManager->expects($this->once())->method('flush');

        $subscriber->onLoginSuccess($event);

        self::assertNull($operator->getLastLoginAt());
        self::assertNull($operator->getLastLoginIp());
    }

    public function testRecordsLastLoginForIncompleteProfile(): void
    {
        $user = $this->createActiveUser();
        $user->setStatus(UserStatus::UncompleteProfileInfo);

        $subscriber = $this->createSubscriberWithHomeRedirect();
        $event = $this->createLoginSuccessEvent($user, $this->createLoginRequest($user->getNickname()));

        $this->entityManager->expects($this->once())->method('flush');

        $subscriber->onLoginSuccess($event);

        self::assertNotNull($user->getLastLoginAt());
        self::assertSame('127.0.0.1', $user->getLastLoginIp());
    }

    private function createLoginRequest(string $nickname): Request
    {
        $request = Request::create('/admin/login', 'POST', [
            '_username' => $nickname,
        ]);
        $request->attributes->set('_route', 'app_admin_login');

        return $request;
    }

    private function createSubscriberWithHomeRedirect(): AdminSecuritySubscriber
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/admin/pokemons');

        return new AdminSecuritySubscriber(
            $this->userRepository,
            $this->entityManager,
            $this->createMock(TokenStorageInterface::class),
            $urlGenerator,
        );
    }

    private function createLoginSuccessEvent(
        User $user,
        Request $request,
        ?TokenInterface $authenticatedToken = null,
    ): LoginSuccessEvent {
        $passport = new SelfValidatingPassport(new UserBadge(
            $user->getUserIdentifier(),
            static fn (): User => $user,
        ));

        return new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            $passport,
            $authenticatedToken ?? $this->createMock(TokenInterface::class),
            $request,
            null,
            'main',
        );
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
