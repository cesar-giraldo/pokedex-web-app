<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Module;
use App\Entity\User;
use App\Module\EventSubscriber\ModuleAccessSubscriber;
use App\Repository\ModuleRepository;
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

    private const string PARENT_SLUG = 'module_admin_secure_area';

    private const string RECOVERY_SLUG = 'module_admin_password_recovery';

    /** @var list<string> */
    private array $nicknames = [];

    private ?bool $originalParentEnabled = null;

    private ?bool $originalRecoveryEnabled = null;

    private ?int $originalRecoveryParentId = null;

    private bool $recoveryHadNoParent = false;

    private bool $createdParent = false;

    private bool $createdRecovery = false;

    protected function tearDown(): void
    {
        if (self::$booted) {
            $this->restoreRecoveryModule();

            if ([] !== $this->nicknames) {
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
        }

        parent::tearDown();
    }

    public function testLoginLinksToRecoveryAndBlockedAccountsStaySilent(): void
    {
        $client = static::createClient();
        $this->setRecoveryModuleState(true, true);
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
        self::assertSelectorTextContains('body', 'Te hemos enviado un correo electrónico para recuperar tu contraseña.');

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
        self::assertSelectorTextContains('body', 'Te hemos enviado un correo electrónico para recuperar tu contraseña.');
    }

    public function testOperatorCanSetANewPasswordAndTheOldSessionStopsWorking(): void
    {
        $client = static::createClient();
        $this->setRecoveryModuleState(true, true);
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
        $form['reset_password[plainPassword]'] = 'SecurePass12';
        $form['reset_password[confirmPassword]'] = 'SecurePass12';
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
            '_password' => 'SecurePass12',
        ]);
        self::assertResponseRedirects('/admin/home');

        $client->request('GET', $path);
        self::assertSelectorTextContains('h1', 'Enlace no disponible');
    }

    public function testRecoveryIsHiddenWhenTheModuleIsDisabled(): void
    {
        $client = static::createClient();
        $this->setRecoveryModuleState(true, false);

        $client->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/admin/forgot-password"]');

        $this->assertRecoveryDenied($client, '/admin/forgot-password');
        $this->assertRecoveryDenied($client, '/admin/reset-password/not-a-valid-token');
    }

    public function testRecoveryIsHiddenWhenTheParentModuleIsDisabled(): void
    {
        $client = static::createClient();
        $this->setRecoveryModuleState(false, true);

        $client->request('GET', '/admin/login');
        self::assertSelectorNotExists('a[href="/admin/forgot-password"]');
        $this->assertRecoveryDenied($client, '/admin/forgot-password');
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

    private function assertRecoveryDenied(KernelBrowser $client, string $path): void
    {
        $client->request('GET', $path);

        self::assertResponseRedirects('/admin/home');
        $client->followRedirect();
        self::assertResponseRedirects('/admin/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', ModuleAccessSubscriber::DENIED_MESSAGE);
        self::assertSelectorNotExists('a[href="/admin/forgot-password"]');
    }

    private function setRecoveryModuleState(bool $parentEnabled, bool $recoveryEnabled): void
    {
        $entityManager = $this->entityManager();
        $repository = $this->moduleRepository();
        $parent = $repository->findOneBy(['slug' => self::PARENT_SLUG]);

        if (!$parent instanceof Module) {
            $parent = new Module('Admin Secure Area', self::PARENT_SLUG, '2.00');
            $entityManager->persist($parent);
            $this->createdParent = true;
        } elseif (null === $this->originalParentEnabled) {
            $this->originalParentEnabled = $parent->isEnabled();
        }

        $recovery = $repository->findOneBy(['slug' => self::RECOVERY_SLUG]);

        if (!$recovery instanceof Module) {
            $recovery = new Module('Recuperación de Contraseña', self::RECOVERY_SLUG, '2.10');
            $recovery->setParent($parent);
            $entityManager->persist($recovery);
            $this->createdRecovery = true;
        } elseif (null === $this->originalRecoveryEnabled) {
            $this->originalRecoveryEnabled = $recovery->isEnabled();
            $recoveryParent = $recovery->getParent();

            if (!$recoveryParent instanceof Module) {
                $this->recoveryHadNoParent = true;
                $recovery->setParent($parent);
            } elseif (self::PARENT_SLUG !== $recoveryParent->getSlug()) {
                $this->originalRecoveryParentId = $recoveryParent->getId();
                $recovery->setParent($parent);
            }
        }

        $parent->setIsEnabled($parentEnabled);
        $recovery->setIsEnabled($recoveryEnabled);
        $entityManager->flush();
    }

    private function restoreRecoveryModule(): void
    {
        if (null === $this->originalParentEnabled && null === $this->originalRecoveryEnabled && !$this->createdParent && !$this->createdRecovery) {
            return;
        }

        $entityManager = $this->entityManager();
        $repository = $this->moduleRepository();
        $recovery = $repository->findOneBy(['slug' => self::RECOVERY_SLUG]);
        $parent = $repository->findOneBy(['slug' => self::PARENT_SLUG]);

        if ($this->createdRecovery && $recovery instanceof Module) {
            $recovery->setParent(null);
            $entityManager->flush();
            $entityManager->remove($recovery);
            $entityManager->flush();
            $recovery = null;
        }

        if ($this->createdParent && $parent instanceof Module) {
            $entityManager->remove($parent);
            $entityManager->flush();
            $parent = null;
        }

        if (!$this->createdRecovery && $recovery instanceof Module) {
            if (null !== $this->originalRecoveryEnabled) {
                $recovery->setIsEnabled($this->originalRecoveryEnabled);
            }

            if ($this->recoveryHadNoParent) {
                $recovery->setParent(null);
            } elseif (null !== $this->originalRecoveryParentId) {
                $originalParent = $entityManager->find(Module::class, $this->originalRecoveryParentId);

                if ($originalParent instanceof Module) {
                    $recovery->setParent($originalParent);
                }
            }
        }

        if (!$this->createdParent && $parent instanceof Module && null !== $this->originalParentEnabled) {
            $parent->setIsEnabled($this->originalParentEnabled);
        }

        $entityManager->flush();
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function moduleRepository(): ModuleRepository
    {
        /** @var ModuleRepository $repository */
        $repository = static::getContainer()->get(ModuleRepository::class);

        return $repository;
    }

    private function remember(string $nickname): string
    {
        $nickname = User::normalizeNickname($nickname);
        $this->nicknames[] = $nickname;

        return $nickname;
    }
}
