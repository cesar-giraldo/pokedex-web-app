<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

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

        self::assertResponseRedirects('/admin/pokemons');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
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
            ->setStatus($status);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();
    }
}
