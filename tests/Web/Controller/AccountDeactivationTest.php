<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\DateTimeFormatter;
use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use App\Tests\Web\Support\AcceptsPendingLegalDocumentsTrait;
use App\Tests\Web\Support\EnablesWebSecureAreaTrait;
use App\Web\Service\AccountDeactivationService;
use App\Web\Service\PublicLanguageResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

use function bin2hex;
use function in_array;
use function random_bytes;
use function random_int;
use function sprintf;
use function str_contains;

#[Group('functional')]
final class AccountDeactivationTest extends WebTestCase
{
    use AcceptsPendingLegalDocumentsTrait;
    use AdminAuthenticatedClientTrait;
    use EnablesWebSecureAreaTrait;

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

        if (static::$booted) {
            $this->restoreWebSecureArea();
        }

        parent::tearDown();
    }

    public function testProfileShowsTheAccountStatusAndDeactivatesWithTheCurrentPassword(): void
    {
        $client = $this->createClientWithSecureArea();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('off' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Active);
        $user = $this->reload($nickname);
        $client->loginUser($user, 'public');

        $crawler = $client->request('GET', '/' . $locale . '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#profile-account-status-title', $this->trans($locale, 'account.status_title'));
        self::assertSelectorTextContains('main', $this->statusSummary($locale, $user, 'account.status.active'));
        self::assertSelectorExists('button.btn-danger[data-action="account-deactivation-dialog#open"]');
        self::assertSelectorExists('#deactivate-current-password');
        self::assertSelectorTextContains('dialog.account-deactivation-dialog', $this->trans($locale, 'account.deactivate_confirm'));

        $form = $crawler->filter('form[action$="/profile/deactivate"]')->form();
        $form['current_password'] = self::PASSWORD;
        $client->submit($form);

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertTrue(str_contains($location, '/logout'), $location);
        $client->followRedirect();
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main [role="status"]', $this->trans($locale, 'account.deactivated'));

        $deactivated = $this->reload($nickname);
        self::assertSame(UserStatus::Inactive, $deactivated->getStatus());
        $notes = $deactivated->getAdditionalNotes();
        self::assertIsString($notes);
        self::assertStringContainsString(AccountDeactivationService::MANUAL_NOTE, $notes);

        $client->request('GET', '/' . $locale . '/profile');
        self::assertResponseRedirects('/' . $locale . '/login');
    }

    public function testWrongPasswordDoesNotDeactivateTheAccount(): void
    {
        $client = $this->createClientWithSecureArea();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('bad' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Active);
        $client->loginUser($this->reload($nickname), 'public');

        $crawler = $client->request('GET', '/' . $locale . '/profile');
        $form = $crawler->filter('form[action$="/profile/deactivate"]')->form();
        $form['current_password'] = 'not-the-password';
        $client->submit($form);

        self::assertResponseRedirects('/' . $locale . '/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="alert"]', $this->trans($locale, 'account.deactivate_password_invalid'));

        $user = $this->reload($nickname);
        self::assertSame(UserStatus::Active, $user->getStatus());
        self::assertNull($user->getAdditionalNotes());
    }

    public function testAccountWithoutAChosenPasswordDeactivatesFromTheDialog(): void
    {
        $client = $this->createClientWithSecureArea();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('ggl' . bin2hex(random_bytes(3)));
        $user = $this->createUser($nickname, [UserRole::User], UserStatus::Active);
        $user->setPasswordChosen(false);
        $this->entityManager()->flush();
        $client->loginUser($this->reload($nickname), 'public');

        $crawler = $client->request('GET', '/' . $locale . '/profile');
        self::assertSelectorNotExists('#deactivate-current-password');
        self::assertSelectorExists('form[action$="/profile/deactivate"]');

        $client->submit($crawler->filter('form[action$="/profile/deactivate"]')->form());

        self::assertResponseRedirects();
        $client->followRedirect();
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="status"]', $this->trans($locale, 'account.deactivated'));
        self::assertSame(UserStatus::Inactive, $this->reload($nickname)->getStatus());
    }

    public function testIncompleteProfileCanDeactivate(): void
    {
        $client = $this->createClientWithSecureArea();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('inc' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::UncompleteProfileInfo);
        $user = $this->reload($nickname);
        $client->loginUser($user, 'public');

        $crawler = $client->request('GET', '/' . $locale . '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $this->statusSummary($locale, $user, 'account.status.uncomplete_profile_info'));

        $form = $crawler->filter('form[action$="/profile/deactivate"]')->form();
        $form['current_password'] = self::PASSWORD;
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        $client->followRedirect();
        self::assertSame(UserStatus::Inactive, $this->reload($nickname)->getStatus());
    }

    public function testInvalidCsrfTokenDoesNotDeactivateTheAccount(): void
    {
        $client = $this->createClientWithSecureArea();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('csrf' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Active);
        $client->loginUser($this->reload($nickname), 'public');
        $client->request('GET', '/' . $locale . '/profile');

        $client->request('POST', '/' . $locale . '/profile/deactivate', [
            '_token' => 'invalid-token',
            'current_password' => self::PASSWORD,
        ]);

        self::assertResponseRedirects('/' . $locale . '/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="alert"]', $this->trans($locale, 'account.deactivate_invalid'));
        self::assertSelectorTextNotContains('main [role="alert"]', $this->trans($locale, 'account.deactivate_forbidden'));
        self::assertSame(UserStatus::Active, $this->reload($nickname)->getStatus());
    }

    public function testBackendRoleSeesTheStatusButCannotDeactivate(): void
    {
        $client = $this->createClientWithSecureArea();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('ops' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::Operator, UserRole::User], UserStatus::Active);
        $client->loginUser($this->reload($nickname), 'public');

        $client->request('GET', '/' . $locale . '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#profile-account-status-title', $this->trans($locale, 'account.status_title'));
        self::assertSelectorNotExists('form[action$="/profile/deactivate"]');
        self::assertSelectorNotExists('button.btn-danger');

        $client->request('POST', '/' . $locale . '/profile/deactivate', [
            '_token' => $this->csrfToken($client, 'deactivate_account'),
            'current_password' => self::PASSWORD,
        ]);

        self::assertResponseRedirects('/' . $locale . '/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="alert"]', $this->trans($locale, 'account.deactivate_forbidden'));
        self::assertSame(UserStatus::Active, $this->reload($nickname)->getStatus());
    }

    public function testImpersonationCannotDeactivateTheAccount(): void
    {
        $client = $this->createClientWithSecureArea();
        $locale = $this->defaultLocale();
        $this->loginAsAdmin($client);
        $user = $this->createUser('wimp', [UserRole::User], UserStatus::Active);
        $nickname = $user->getNickname();

        $crawler = $client->request('GET', '/admin/users', [
            'limit' => 'all',
            'search_user' => [
                'roles' => [UserRole::User->value],
            ],
        ]);
        $form = $crawler->filter(sprintf('form[action$="/%d/impersonate-web"]', $user->getId()))->form();
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();

        $client->request('GET', '/' . $locale . '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#profile-account-status-title', $this->trans($locale, 'account.status_title'));
        self::assertSelectorNotExists('form[action$="/profile/deactivate"]');

        $client->request('POST', '/' . $locale . '/profile/deactivate', [
            '_token' => $this->csrfToken($client, 'deactivate_account'),
            'current_password' => self::PASSWORD,
        ]);

        self::assertResponseRedirects('/' . $locale . '/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="alert"]', $this->trans($locale, 'account.deactivate_forbidden'));
        self::assertSame(UserStatus::Active, $this->reload($nickname)->getStatus());
    }

    /**
     * @param list<UserRole> $roles
     */
    private function createUser(string $nickname, array $roles, UserStatus $status): User
    {
        $entityManager = $this->entityManager();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $nickname = $this->rememberNickname($nickname);
        $existing = $users->findOneByNickname($nickname);

        if ($existing instanceof User) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }

        $cellphone = $this->phone();
        $existingPhone = $users->findOneBy([
            'countryCode' => 57,
            'cellphone' => $cellphone,
        ]);

        if ($existingPhone instanceof User) {
            $entityManager->remove($existingPhone);
            $entityManager->flush();
        }

        $user = new User()
            ->setName('Ada')
            ->setLastname('Tester')
            ->setEmail($nickname . '@example.com')
            ->setNickname($nickname)
            ->setCountryCode(57)
            ->setCellphone($cellphone)
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
        $this->entityManager()->clear();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function rememberNickname(string $nickname): string
    {
        $nickname = User::normalizeNickname($nickname);

        if (!in_array($nickname, $this->nicknames, true)) {
            $this->nicknames[] = $nickname;
        }

        return $nickname;
    }

    private function phone(): string
    {
        return (string) random_int(3000000000, 3999999999);
    }

    private function csrfToken(KernelBrowser $client, string $tokenId): string
    {
        $session = $client->getRequest()->getSession();
        $value = bin2hex(random_bytes(16));
        $session->set('_csrf/' . $tokenId, $value);
        $session->save();

        return $value;
    }

    private function statusSummary(string $locale, User $user, string $statusKey): string
    {
        /** @var DateTimeFormatter $formatter */
        $formatter = static::getContainer()->get(DateTimeFormatter::class);

        return $this->trans($locale, 'account.status_summary', [
            'date' => $formatter->format($user->getCreatedAt(), viewer: $user),
            'status' => $this->trans($locale, $statusKey),
        ]);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function trans(string $locale, string $key, array $parameters = []): string
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);
        /** @var LocaleSwitcher $localeSwitcher */
        $localeSwitcher = static::getContainer()->get(LocaleSwitcher::class);

        return $localeSwitcher->runWithLocale(
            $locale,
            static fn (): string => $translator->trans($key, $parameters),
        );
    }

    private function defaultLocale(): string
    {
        /** @var GeneralSettingsProvider $settings */
        $settings = static::getContainer()->get(GeneralSettingsProvider::class);
        /** @var PublicLanguageResolver $languages */
        $languages = static::getContainer()->get(PublicLanguageResolver::class);

        return $languages->defaultLanguage($settings->get())->value;
    }
}
