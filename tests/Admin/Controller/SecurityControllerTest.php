<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

#[Group('functional')]
final class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIsAccessibleForGuests(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Iniciar sesión');
    }

    public function testActiveAdminCanLogin(): void
    {
        $client = static::createClient();
        $this->createUser('admin-login', 'Secret123', UserRole::Admin, UserStatus::Active);

        $client->request('GET', '/admin/login');
        $client->submitForm('Iniciar sesión', [
            '_username' => 'admin-login',
            '_password' => 'Secret123',
        ]);

        self::assertResponseRedirects('/admin/home');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $loggedInUser = $userRepository->findOneByNickname('admin-login');
        self::assertInstanceOf(User::class, $loggedInUser);
        self::assertNotNull($loggedInUser->getLastLoginAt());
        self::assertNotNull($loggedInUser->getLastLoginIp());
    }

    public function testUnknownNicknameShowsGenericInvalidCredentialsMessage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/login');
        $client->submitForm('Iniciar sesión', [
            '_username' => 'non-existent-user',
            '_password' => 'Secret123',
        ]);

        self::assertResponseRedirects('/admin/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Credenciales inválidas');
        self::assertSelectorTextNotContains('body', 'non-existent-user');
    }

    public function testLogoutClearsRememberMeCookieAndSession(): void
    {
        $client = static::createClient();
        $this->createUser('admin-login', 'Secret123', UserRole::Admin, UserStatus::Active);

        $client->request('GET', '/admin/login');
        $client->submitForm('Iniciar sesión', [
            '_username' => 'admin-login',
            '_password' => 'Secret123',
            '_remember_me' => 'on',
        ]);

        self::assertResponseRedirects('/admin/home');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $rememberMeCookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($rememberMeCookie);
        self::assertFalse($rememberMeCookie->isExpired());

        $client->request('GET', '/admin/logout');
        self::assertResponseRedirects('/admin/login');
        $client->followRedirect();

        $rememberMeCookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertTrue(null === $rememberMeCookie || $rememberMeCookie->isExpired());

        $client->request('GET', '/admin/pokemons');
        self::assertResponseRedirects('/admin/login');
    }

    public function testUnconfirmedAccountCannotLogin(): void
    {
        $client = static::createClient();
        $this->createUser('pending-user', 'Secret123', UserRole::Admin, UserStatus::UnconfirmedAccount);

        $client->request('GET', '/admin/login');
        $client->submitForm('Iniciar sesión', [
            '_username' => 'pending-user',
            '_password' => 'Secret123',
        ]);

        self::assertResponseRedirects('/admin/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Debes confirmar tu cuenta antes de iniciar sesión.');
    }

    public function testWebUserCannotAccessBackendAfterLoginAttempt(): void
    {
        $client = static::createClient();
        $this->createUser('web-user', 'Secret123', UserRole::User, UserStatus::Active);

        $client->request('GET', '/admin/login');
        $client->submitForm('Iniciar sesión', [
            '_username' => 'web-user',
            '_password' => 'Secret123',
        ]);

        self::assertResponseRedirects('/admin/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No tienes acceso al panel de administración.');

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $webUser = $userRepository->findOneByNickname('web-user');
        self::assertInstanceOf(User::class, $webUser);
        self::assertNull($webUser->getLastLoginAt());
        self::assertNull($webUser->getLastLoginIp());
    }

    private function createUser(
        string $nickname,
        string $plainPassword,
        UserRole $role,
        UserStatus $status,
    ): void {
        $container = static::getContainer();

        $user = new User()
            ->setName('Test')
            ->setLastname('User')
            ->setEmail(sprintf('%s@example.com', $nickname))
            ->setNickname($nickname)
            ->setApplicationRoles([$role])
            ->setStatus($status)
            ->setIsHidden(true);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $existingUser = $userRepository->findOneByNickname($nickname);
        if ($existingUser instanceof User) {
            $entityManager->remove($existingUser);
            $entityManager->flush();
        }

        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();
    }
}
