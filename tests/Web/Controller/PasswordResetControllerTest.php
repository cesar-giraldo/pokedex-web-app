<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Web\Support\AcceptsPendingLegalDocumentsTrait;
use App\Web\Service\PublicLanguageResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

use function bin2hex;
use function preg_match;
use function random_bytes;

#[Group('functional')]
final class PasswordResetControllerTest extends WebTestCase
{
    use AcceptsPendingLegalDocumentsTrait;
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

    public function testLoginLinksToTheRequestPageAndUnknownOrBannedAccountsGetTheSameReply(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $sent = $this->trans($locale, 'auth.forgot_password_sent');
        $crawler = $client->request('GET', '/' . $locale . '/login');
        self::assertResponseIsSuccessful();
        $link = $crawler->selectLink($this->trans($locale, 'auth.forgot_password'));
        self::assertSame(1, $link->count());

        $client->click($link->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $this->trans($locale, 'auth.forgot_password_title'));

        $this->submitIdentifier($client, $locale, 'missing-reset@example.com');
        self::assertQueuedEmailCount(0);
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="status"]', $sent);

        $nickname = $this->rememberNickname('ban' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Banned, $nickname . '@example.com');
        $this->submitIdentifier($client, $locale, $nickname . '@example.com');
        self::assertQueuedEmailCount(0);
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="status"]', $sent);
    }

    public function testActiveUserResetsFromEmailAndThePreviousSessionStopsWorking(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('rst' . bin2hex(random_bytes(3)));
        $email = $nickname . '@example.com';
        $user = $this->createUser($nickname, [UserRole::User], UserStatus::Active, $email);
        $user->setPasswordChosen(false);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $this->submitLogin($client, $locale, $nickname, self::PASSWORD);
        $client->followRedirect();
        self::assertNotNull($client->getRequest()->getSession()->get('_security_public'));

        $this->submitIdentifier($client, $locale, strtoupper($email));
        $firstPath = $this->resetPath(self::getMailerMessage(0));
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="status"]', $this->trans($locale, 'auth.forgot_password_sent'));

        $this->submitIdentifier($client, $locale, $email);
        $secondPath = $this->resetPath(self::getMailerMessage(0));
        $client->followRedirect();
        self::assertNotSame($firstPath, $secondPath);

        $client->request('GET', $firstPath);
        self::assertSelectorTextContains('h1', $this->trans($locale, 'auth.reset_password_invalid_title'));
        self::assertSelectorExists('meta[name="referrer"][content="no-referrer"]');

        $crawler = $client->request('GET', $secondPath);
        self::assertSelectorExists('meta[name="referrer"][content="no-referrer"]');
        self::assertSelectorTextContains('h1', $this->trans($locale, 'auth.reset_password_title'));
        $this->submitPasswords($client, $crawler, self::PASSWORD, self::PASSWORD);
        self::assertSelectorTextContains('body', $this->trans($locale, 'auth.password_same_as_current', 'validators'));
        $crawler = $client->getCrawler();
        $this->submitPasswords($client, $crawler, 'Newpass1', 'Otherpass1');
        self::assertSelectorTextContains('body', $this->trans($locale, 'auth.password_mismatch', 'validators'));
        $crawler = $client->getCrawler();
        $this->submitPasswords($client, $crawler, 'abcdef', 'abcdef');
        self::assertSelectorTextContains('body', $this->trans($locale, 'auth.password_strength', 'validators'));
        $crawler = $client->getCrawler();
        $this->submitPasswords($client, $crawler, 'Newpass1', 'Newpass1');
        $completed = self::getMailerMessage(0);

        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="status"]', $this->trans($locale, 'auth.reset_password_success'));
        self::assertNull($client->getRequest()->getSession()->get('_security_public'));
        self::assertInstanceOf(TemplatedEmail::class, $completed);
        self::assertEmailHtmlBodyContains($completed, $this->trans($locale, 'email.password_reset_completed.heading'));
        self::assertEmailHtmlBodyNotContains($completed, 'Newpass1');

        $updated = $this->reload($nickname);
        self::assertTrue($updated->hasChosenPassword());
        self::assertSame(UserStatus::Active, $updated->getStatus());
        self::assertNull($updated->getNoLoginUntil());

        $client->request('GET', '/' . $locale . '/profile');
        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');

        $this->submitLogin($client, $locale, $nickname, 'Newpass1');
        $this->assertRedirectEndsWith($client, '/' . $locale);
        $client->request('GET', '/' . $locale . '/logout');
        $this->submitLogin($client, $locale, $nickname, self::PASSWORD);
        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');

        $client->request('GET', $secondPath);
        self::assertSelectorTextContains('h1', $this->trans($locale, 'auth.reset_password_invalid_title'));
        $client->request('GET', '/' . $locale . '/reset-password/not-a-valid-reset-link');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $this->trans($locale, 'auth.reset_password_invalid_title'));
    }

    public function testUnconfirmedAccountKeepsItsStatusAndStaffCannotUseThePublicForm(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('unc' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::UnconfirmedAccount, $nickname . '@example.com');
        $this->submitIdentifier($client, $locale, $nickname);
        $path = $this->resetPath(self::getMailerMessage(0));
        $crawler = $client->request('GET', $path);
        $this->submitPasswords($client, $crawler, 'Newpass1', 'Newpass1');
        $client->followRedirect();
        $this->submitLogin($client, $locale, $nickname, 'Newpass1');
        $this->assertRedirectEndsWith($client, '/' . $locale . '/account/confirm');
        self::assertSame(UserStatus::UnconfirmedAccount, $this->reload($nickname)->getStatus());

        $client->request('GET', '/' . $locale . '/logout');
        $staff = $this->rememberNickname('stf' . bin2hex(random_bytes(3)));
        $this->createUser($staff, [UserRole::Operator], UserStatus::Active, $staff . '@example.com');
        $this->submitIdentifier($client, $locale, $staff . '@example.com');
        self::assertQueuedEmailCount(0);
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="status"]', $this->trans($locale, 'auth.forgot_password_sent'));
    }

    private function submitIdentifier(KernelBrowser $client, string $locale, string $identifier): void
    {
        $crawler = $client->request('GET', '/' . $locale . '/forgot-password');
        $form = $crawler->filter('form')->form();
        $form['forgot_password[identifier]'] = $identifier;
        $client->submit($form);
    }

    private function submitPasswords(KernelBrowser $client, \Symfony\Component\DomCrawler\Crawler $crawler, string $password, string $confirm): void
    {
        $form = $crawler->filter('form')->form();
        $form['reset_password[plainPassword]'] = $password;
        $form['reset_password[confirmPassword]'] = $confirm;
        $client->submit($form);
    }

    private function submitLogin(KernelBrowser $client, string $locale, string $nickname, string $password): void
    {
        $crawler = $client->request('GET', '/' . $locale . '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = $nickname;
        $form['_password'] = $password;
        $client->submit($form);
    }

    private function assertRedirectEndsWith(KernelBrowser $client, string $path): void
    {
        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertTrue(str_ends_with($location, $path), $location);
    }

    private function resetPath(mixed $message): string
    {
        self::assertInstanceOf(TemplatedEmail::class, $message);
        $html = $message->getHtmlBody();
        self::assertIsString($html);
        $matched = preg_match('#https?://[^/"\s]+(/[^"\s]+/reset-password/[a-f0-9]{32}\.[a-f0-9]{64})#', $html, $matches);
        self::assertSame(1, $matched);
        self::assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    /**
     * @param list<UserRole> $roles
     */
    private function createUser(string $nickname, array $roles, UserStatus $status, string $email): User
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
            ->setName('Ada')
            ->setLastname('Tester')
            ->setNickname($nickname)
            ->setEmail($email)
            ->setApplicationRoles($roles)
            ->setStatus($status);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $entityManager->persist($user);
        $entityManager->flush();

        if (UserStatus::Active === $status && $user->hasAssignedRole(UserRole::User)) {
            $this->acceptPendingLegalDocuments($user);
        }

        return $user;
    }

    private function reload(string $nickname): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function rememberNickname(string $nickname): string
    {
        $nickname = User::normalizeNickname($nickname);
        $this->nicknames[] = $nickname;

        return $nickname;
    }

    private function defaultLocale(): string
    {
        /** @var GeneralSettingsProvider $settings */
        $settings = static::getContainer()->get(GeneralSettingsProvider::class);
        /** @var PublicLanguageResolver $languages */
        $languages = static::getContainer()->get(PublicLanguageResolver::class);

        return $languages->defaultLanguage($settings->get())->value;
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function trans(string $locale, string $key, string $domain = 'messages', array $parameters = []): string
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);
        /** @var LocaleSwitcher $localeSwitcher */
        $localeSwitcher = static::getContainer()->get(LocaleSwitcher::class);

        return $localeSwitcher->runWithLocale(
            $locale,
            static fn (): string => $translator->trans($key, $parameters, $domain),
        );
    }
}
