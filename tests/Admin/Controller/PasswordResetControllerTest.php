<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function bin2hex;
use function preg_match;
use function random_bytes;

#[Group('functional')]
final class PasswordResetControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private const string PASSWORD = 'Secret123';

    /** @var list<string> */
    private array $nicknames = [];

    protected function tearDown(): void
    {
        if ([] !== $this->nicknames && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            /** @var UserRepository $users */
            $users = static::getContainer()->get(UserRepository::class);

            foreach ($this->nicknames as $nickname) {
                $user = $users->findOneByNickname($nickname);

                if ($user instanceof User) {
                    $entityManager->remove($user);
                }
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testLoginLinksToRecoveryAndBlockedAccountsStaySilent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();
        $link = $crawler->selectLink('¿Olvidaste tu contraseña?');
        self::assertSame(1, $link->count());
        $client->click($link->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Restablecer contraseña');

        $this->submitIdentifier($client, 'missing-admin@example.com');
        self::assertQueuedEmailCount(0);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Si la cuenta existe y se puede recuperar, enviamos un correo con los pasos.');

        $webOnly = $this->remember('web' . bin2hex(random_bytes(3)));
        $this->createUser($webOnly, UserRole::User, UserStatus::Active);
        $banned = $this->remember('ban' . bin2hex(random_bytes(3)));
        $this->createUser($banned, UserRole::Operator, UserStatus::Banned);

        $this->submitIdentifier($client, $webOnly . '@example.com');
        self::assertQueuedEmailCount(0);
        $client->followRedirect();
        $this->submitIdentifier($client, $banned);
        self::assertQueuedEmailCount(0);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Si la cuenta existe y se puede recuperar, enviamos un correo con los pasos.');
    }

    public function testOperatorCanSetANewPasswordAndTheOldSessionStopsWorking(): void
    {
        $client = static::createClient();
        $nickname = $this->remember('op' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, UserRole::Operator, UserStatus::Active);
        $client->request('GET', '/admin/login');
        $client->submitForm('Iniciar sesión', [
            '_username' => $nickname,
            '_password' => self::PASSWORD,
            '_remember_me' => 'on',
        ]);
        self::assertResponseRedirects('/admin/home');
        $client->followRedirect();
        self::assertNotNull($client->getCookieJar()->get('REMEMBERME'));

        $this->submitIdentifier($client, $nickname . '@example.com');
        $path = $this->resetPath(self::getMailerMessage(0));
        $crawler = $client->request('GET', $path);
        self::assertSelectorExists('meta[name="referrer"][content="no-referrer"]');
        self::assertSelectorTextContains('h1', 'Crea una contraseña nueva');
        $form = $crawler->filter('form')->form();
        $form['reset_password[plainPassword]'] = 'Newpass1';
        $form['reset_password[confirmPassword]'] = 'Newpass1';
        $client->submit($form);

        self::assertResponseRedirects('/admin/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Tu contraseña se actualizó. Entra con la nueva.');
        $client->request('GET', '/admin/home');
        self::assertResponseRedirects('/admin/login');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = static::getContainer()->get('session.factory');
        $client->getCookieJar()->expire($sessionFactory->createSession()->getName());
        $client->request('GET', '/admin/home');
        self::assertResponseRedirects('/admin/login');

        $client->request('GET', '/admin/login');
        $client->submitForm('Iniciar sesión', [
            '_username' => $nickname,
            '_password' => 'Newpass1',
        ]);
        self::assertResponseRedirects('/admin/home');

        $client->request('GET', $path);
        self::assertSelectorTextContains('h1', 'Enlace no disponible');
    }

    private function submitIdentifier(KernelBrowser $client, string $identifier): void
    {
        $crawler = $client->request('GET', '/admin/forgot-password');
        $form = $crawler->filter('form')->form();
        $form['forgot_password[identifier]'] = $identifier;
        $client->submit($form);
    }

    private function resetPath(mixed $message): string
    {
        self::assertInstanceOf(TemplatedEmail::class, $message);
        $html = $message->getHtmlBody();
        self::assertIsString($html);
        $matched = preg_match('#https?://[^"\s]+(/admin/reset-password/[a-f0-9]{32}\.[a-f0-9]{64})#', $html, $matches);
        self::assertSame(1, $matched);
        self::assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    private function createUser(string $nickname, UserRole $role, UserStatus $status): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $existing = $users->findOneByNickname($nickname);

        if ($existing instanceof User) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }

        $user = new User()
            ->setName('Test')
            ->setLastname('User')
            ->setEmail($nickname . '@example.com')
            ->setNickname($nickname)
            ->setApplicationRoles([$role])
            ->setStatus($status)
            ->setIsHidden(true);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $entityManager->persist($user);
        $entityManager->flush();
    }

    private function remember(string $nickname): string
    {
        $nickname = User::normalizeNickname($nickname);
        $this->nicknames[] = $nickname;

        return $nickname;
    }
}
