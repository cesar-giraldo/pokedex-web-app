<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\DateTimeFormatter;
use App\Admin\Service\GeneralSettingsProvider;
use App\Admin\Service\Storage\UserProfileImageStorage;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\GeneralSettingsRepository;
use App\Repository\UserRepository;
use App\Tests\Web\Support\AcceptsPendingLegalDocumentsTrait;
use App\Web\Service\PublicLanguageResolver;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

use function bin2hex;
use function imagecreatetruecolor;
use function imagejpeg;
use function random_bytes;
use function random_int;
use function sprintf;
use function tempnam;

#[Group('functional')]
final class PublicAuthenticationTest extends WebTestCase
{
    use AcceptsPendingLegalDocumentsTrait;

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
            /** @var UserProfileImageStorage $storage */
            $storage = static::getContainer()->get(UserProfileImageStorage::class);

            foreach ($this->nicknames as $nickname) {
                $user = $users->findOneByNickname($nickname);

                if (!$user instanceof User) {
                    continue;
                }

                $imagePath = $user->getProfileImagePath();

                if (null !== $imagePath && '' !== $imagePath) {
                    $storage->tryDelete($imagePath);
                }

                $entityManager->remove($user);
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testActiveUserLogsInWithNicknameAndSeesTheAccountMenu(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('web' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Active, 'Ada', 'web-' . $nickname . '@example.com', 57, $this->phone());

        $this->submitLogin($client, $locale, $nickname, self::PASSWORD);

        $this->assertRedirectEndsWith($client, '/' . $locale);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.account-menu', 'Ada');
        self::assertSelectorTextContains('.account-menu', 'web-' . $nickname . '@example.com');
        self::assertSelectorExists('.account-menu .border-b');
        self::assertSelectorExists('.account-menu[data-controller="account-menu"]');
        self::assertSelectorExists('.account-menu .account-menu-chevron');
        self::assertSelectorCount(3, '.account-menu .account-menu-icon');
        self::assertSelectorNotExists('.header-login');
        self::assertNotNull($client->getRequest()->getSession()->get('_security_public'));
    }

    public function testLoginAndRootOpenInThePreferredLanguage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var GeneralSettingsRepository $repository */
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        /** @var GeneralSettingsProvider $settingsProvider */
        $settingsProvider = static::getContainer()->get(GeneralSettingsProvider::class);
        $settings = $repository->getOrCreateSingleton();
        $enabled = $settings->getEnabledLanguages();
        $default = $settings->getWebsiteDefaultLanguage();
        $settings->setEnabledLanguages(['es', 'en']);
        $settings->setWebsiteDefaultLanguage('es');
        $entityManager->flush();
        $settingsProvider->resetCache();

        $nickname = $this->rememberNickname('lang' . bin2hex(random_bytes(3)));

        try {
            $user = $this->createUser($nickname, [UserRole::User], UserStatus::Active);
            $user->setPreferredLanguage(SupportedLanguage::English);
            $entityManager->flush();

            $this->submitLogin($client, 'es', $nickname, self::PASSWORD);
            $this->assertRedirectEndsWith($client, '/en');

            $client->loginUser($this->reload($nickname), 'public');
            $client->request('GET', '/');
            $this->assertRedirectEndsWith($client, '/en');
        } finally {
            $settings->setEnabledLanguages($enabled);
            $settings->setWebsiteDefaultLanguage($default);
            $entityManager->flush();
            $settingsProvider->resetCache();
        }
    }

    public function testEmailShapedNicknameLogsInFromTheSameTextField(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('ada' . bin2hex(random_bytes(2)) . '@e.co');
        $this->createUser($nickname, [UserRole::User], UserStatus::Active);

        $this->submitLogin($client, $locale, strtoupper($nickname), self::PASSWORD);

        $this->assertRedirectEndsWith($client, '/' . $locale);
        $client->followRedirect();
        self::assertSelectorTextContains('.account-menu', '@' . $nickname);
    }

    public function testStaffWithoutStoredUserRoleIsRejected(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('op' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::Operator], UserStatus::Active);
        $expected = $this->trans($locale, 'auth.no_public_access');

        $this->submitLogin($client, $locale, $nickname, self::PASSWORD);

        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="alert"]', $expected);
        self::assertNull($client->getRequest()->getSession()->get('_security_public'));
    }

    public function testRejectedStaffLoginClearsTheRememberMeCookie(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('oprm' . bin2hex(random_bytes(2)));
        $this->createUser($nickname, [UserRole::Operator], UserStatus::Active);

        $crawler = $client->request('GET', '/' . $locale . '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = $nickname;
        $form['_password'] = self::PASSWORD;
        $rememberMe = $form['_remember_me'];
        self::assertInstanceOf(ChoiceFormField::class, $rememberMe);
        $rememberMe->tick();
        $client->submit($form);

        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');
        $expiresAt = null;
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            if ('PUBLIC_REMEMBERME' === $cookie->getName()) {
                $expiresAt = $cookie->getExpiresTime();
            }
        }
        self::assertNotNull($expiresAt);
        self::assertLessThan(time(), $expiresAt);

        $client->request('GET', '/' . $locale);
        self::assertResponseIsSuccessful();
    }

    public function testNicknameStaysReadOnlyAndTheSessionRemainsValid(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('nick' . bin2hex(random_bytes(3)));
        $this->createUser(
            $nickname,
            [UserRole::User],
            UserStatus::Active,
            'Ada',
            'nick-' . $nickname . '@example.com',
            57,
            $this->phone(),
        );
        $client->loginUser($this->reload($nickname), 'public');

        $crawler = $client->request('GET', '/' . $locale . '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#profile-nickname[disabled]');
        self::assertSame($nickname, $crawler->filter('#profile-nickname')->attr('value'));
        self::assertSelectorTextContains('#profile-nickname-help', $this->trans($locale, 'account.nickname_readonly'));

        $form = $crawler->filter('form[name="user_profile_info"]')->form();
        $values = $form->getPhpValues();
        $values['user_profile_info']['name'] = 'Nova';
        $values['user_profile_info']['nickname'] = 'other-nickname';
        $client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

        $this->assertRedirectEndsWith($client, '/' . $locale . '/profile');
        $updated = $this->reload($nickname);
        self::assertSame($nickname, $updated->getNickname());
        self::assertSame('Nova', $updated->getName());

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#profile-nickname[disabled]');
        self::assertSame($nickname, $client->getCrawler()->filter('#profile-nickname')->attr('value'));
    }

    public function testAccountWithUserRoleBesidesAHigherRoleCanLogIn(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('both' . bin2hex(random_bytes(2)));
        $this->createUser($nickname, [UserRole::Admin, UserRole::User], UserStatus::Active);

        $this->submitLogin($client, $locale, $nickname, self::PASSWORD);

        $this->assertRedirectEndsWith($client, '/' . $locale);
    }

    public function testDeniedStatusesStayOnTheLoginPage(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();

        /** @var list<array{UserStatus, string}> $cases */
        $cases = [
            [UserStatus::UnconfirmedAccount, 'account_unconfirmed'],
            [UserStatus::Banned, 'account_banned'],
            [UserStatus::Inactive, 'account_inactive'],
        ];

        foreach ($cases as [$status, $messageKey]) {
            $nickname = $this->rememberNickname('st' . $status->value[0] . bin2hex(random_bytes(2)));
            $this->createUser($nickname, [UserRole::User], $status);

            $this->submitLogin($client, $locale, $nickname, self::PASSWORD);

            $this->assertRedirectEndsWith($client, '/' . $locale . '/login');
            $client->followRedirect();
            self::assertSelectorTextContains('main [role="alert"]', $this->trans($locale, $messageKey, 'security'));
            self::assertNull($client->getRequest()->getSession()->get('_security_public'));
        }
    }

    public function testTemporaryLockShowsTheTranslatedWaitMessage(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('lock' . bin2hex(random_bytes(2)));
        $user = $this->createUser($nickname, [UserRole::User], UserStatus::Active);
        $user->setNoLoginUntil(new DateTime('+90 minutes'));
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
        $expected = $this->trans($locale, 'login_temporarily_blocked_other', 'security', [
            '%minutes%' => $user->getRemainingLoginLockMinutes(),
        ]);

        $this->submitLogin($client, $locale, $nickname, self::PASSWORD);

        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="alert"]', $expected);
    }

    public function testIncompleteProfileIsSentToProfileAndCannotOpenFavorites(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('inc' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::UncompleteProfileInfo);

        $this->submitLogin($client, $locale, $nickname, self::PASSWORD);

        $this->assertRedirectEndsWith($client, '/' . $locale . '/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', $this->trans($locale, 'account.profile_title'));
        self::assertSelectorExists('main .alert-warning[role="alert"]');
        self::assertSelectorTextContains('main .alert-warning', $this->trans($locale, 'account.complete_profile'));

        $client->request('GET', '/' . $locale);
        $this->assertRedirectEndsWith($client, '/' . $locale . '/profile');

        $client->request('GET', '/' . $locale . '/favorites');
        $this->assertRedirectEndsWith($client, '/' . $locale . '/profile');
    }

    public function testCompletingContactInfoActivatesTheAccount(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('done' . bin2hex(random_bytes(2)));
        $this->createUser($nickname, [UserRole::User], UserStatus::UncompleteProfileInfo);
        $client->loginUser($this->reload($nickname), 'public');

        $crawler = $client->request('GET', '/' . $locale . '/profile');
        $form = $crawler->filter('form[name="user_profile_info"]')->form();
        $form['user_profile_info[email]'] = 'done-' . bin2hex(random_bytes(3)) . '@example.com';
        $form['user_profile_info[countryCode]'] = '57';
        $form['user_profile_info[cellphone]'] = $this->phone();
        $client->submit($form);

        $this->assertRedirectEndsWith($client, '/' . $locale . '/profile');
        $updated = $this->reload($nickname);
        self::assertSame(UserStatus::Active, $updated->getStatus());
        $this->acceptPendingLegalDocuments($updated);
        $client->followRedirect();
        self::assertSelectorNotExists('main .alert-warning');

        $client->request('GET', '/' . $locale . '/favorites');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $this->trans($locale, 'favorites.title'));
        self::assertSelectorTextContains('main', 'Lorem ipsum dolor sit amet');
        self::assertSelectorExists(sprintf(
            'nav[aria-label="%s"] a[href="/%s/pokemon"]',
            $this->trans($locale, 'breadcrumb.label'),
            $locale,
        ));
        self::assertSelectorTextContains(
            sprintf('nav[aria-label="%s"] [aria-current="page"]', $this->trans($locale, 'breadcrumb.label')),
            $this->trans($locale, 'favorites.title'),
        );
    }

    public function testPasswordChangeUpdatesTheHash(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('pwd' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Active, 'Ada', 'pwd-' . $nickname . '@example.com', 57, $this->phone());
        $account = $this->reload($nickname);
        $createdAt = new DateTime('-2 days');
        $account->setCreatedAt($createdAt);
        $account->setPasswordUpdatedAt($createdAt);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
        $client->loginUser($this->reload($nickname), 'public');

        $crawler = $client->request('GET', '/' . $locale . '/profile');
        self::assertSelectorTextContains('#profile-password-updated', $this->trans($locale, 'account.password_unchanged'));
        self::assertSelectorTextContains('#profile-avatar-help', $this->trans($locale, 'account.avatar_help'));
        self::assertSelectorNotExists('#profile-remove-avatar');
        self::assertSelectorCount(3, '[data-controller="password-input"]');
        self::assertSelectorExists('[data-action="click->password-input#toggleVisibility"]');
        self::assertSelectorCount(2, '[data-controller="submit-button"]');
        self::assertSame(
            $this->trans($locale, 'form.processing'),
            $crawler->filter('form[name="user_profile_info"] [data-controller="submit-button"]')->attr('data-submit-button-processing-label-value'),
        );
        $form = $crawler->filter('form[name="user_profile_password"]')->form();
        $form['user_profile_password[currentPassword]'] = self::PASSWORD;
        $form['user_profile_password[plainPassword]'] = 'Secret456';
        $form['user_profile_password[confirmPassword]'] = 'Secret456';
        $client->submit($form);

        $this->assertRedirectEndsWith($client, '/' . $locale . '/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('main [role="status"]', $this->trans($locale, 'account.password_saved'));

        $updated = $this->reload($nickname);
        /** @var DateTimeFormatter $dates */
        $dates = static::getContainer()->get(DateTimeFormatter::class);
        self::assertSelectorTextContains('#profile-password-updated', $this->trans($locale, 'account.password_last_updated', 'messages', [
            'date' => $dates->format($updated->getPasswordUpdatedAt(), '—', $updated),
        ]));

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($updated, 'Secret456'));
    }

    public function testInvalidFieldsRenderErrorMessages(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('err' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Active, 'Ada', 'err-' . $nickname . '@example.com', 57, $this->phone());
        $client->loginUser($this->reload($nickname), 'public');

        $crawler = $client->request('GET', '/' . $locale . '/profile');
        $passwordForm = $crawler->filter('form[name="user_profile_password"]')->form();
        $passwordForm['user_profile_password[currentPassword]'] = 'wrong-password';
        $passwordForm['user_profile_password[plainPassword]'] = 'abc';
        $passwordForm['user_profile_password[confirmPassword]'] = 'abc';
        $client->submit($passwordForm);

        self::assertSelectorExists('#profile-current-password[aria-invalid="true"]');
        self::assertSelectorExists('#profile-current-password-error.field-error');
        self::assertSelectorTextContains('#profile-current-password-error', 'La contraseña actual no es correcta.');
        self::assertSelectorExists('#profile-new-password[aria-invalid="true"]');
        self::assertSelectorExists('#profile-new-password-error.field-error');
        self::assertSelectorTextContains('#profile-new-password-error', 'La contraseña debe tener al menos 5 caracteres e incluir letras y números.');
        self::assertSelectorExists('main .alert-error');
        self::assertSelectorExists('main .alert-error [data-action="alert#dismiss"]');

        $infoForm = $client->getCrawler()->filter('form[name="user_profile_info"]')->form();
        $infoForm['user_profile_info[name]'] = str_repeat('A', 51);
        $infoForm['user_profile_info[timezone]'] = '';
        $client->submit($infoForm);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#profile-name[aria-invalid="true"]');
        self::assertSelectorExists('#profile-name-error.field-error');
        self::assertSelectorTextContains('#profile-name-error', 'Este campo no puede tener más de 50 caracteres.');
        self::assertSelectorExists('#profile-timezone[aria-invalid="true"]');
        self::assertSelectorExists('#profile-timezone-error.field-error');
        self::assertSelectorTextContains('#profile-timezone-error', 'Este valor no debería estar vacío.');
    }

    public function testLogoutClearsThePublicSession(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('out' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Active);
        $client->loginUser($this->reload($nickname), 'public');

        $client->request('GET', '/' . $locale . '/logout');

        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');
        $client->followRedirect();
        self::assertSelectorExists('.header-login');
        self::assertNull($client->getRequest()->getSession()->get('_security_public'));
    }

    public function testFavoritesRequiresAnActiveSession(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();

        $client->request('GET', '/' . $locale . '/favorites');

        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');
    }

    public function testAvatarIsServedOnlyToTheSignedInAccount(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $nickname = $this->rememberNickname('img' . bin2hex(random_bytes(3)));
        $this->createUser($nickname, [UserRole::User], UserStatus::Active, 'Ada', 'img-' . $nickname . '@example.com', 57, $this->phone());

        $client->request('GET', '/' . $locale . '/profile/avatar');
        $this->assertRedirectEndsWith($client, '/' . $locale . '/login');

        $client->loginUser($this->reload($nickname), 'public');
        $client->request('GET', '/' . $locale . '/profile/avatar');
        self::assertResponseStatusCodeSame(404);

        $managedUser = $this->reload($nickname);
        /** @var UserProfileImageStorage $storage */
        $storage = static::getContainer()->get(UserProfileImageStorage::class);
        $managedUser->setProfileImagePath($storage->upload($managedUser, $this->createUploadedFile()));
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $client->request('GET', '/' . $locale . '/profile/avatar');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('image/', (string) $client->getResponse()->headers->get('Content-Type'));

        $client->request('GET', '/' . $locale . '/profile');
        self::assertSelectorExists('#profile-remove-avatar[role="switch"]');
        self::assertSelectorExists('[data-controller="toggle"]');
        self::assertSelectorTextContains('#profile-remove-avatar-help', $this->trans($locale, 'account.remove_avatar_help'));
    }

    private function submitLogin(KernelBrowser $client, string $locale, string $nickname, string $password): void
    {
        $crawler = $client->request('GET', '/' . $locale . '/login');
        self::assertResponseIsSuccessful();
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

    /**
     * @param list<UserRole> $roles
     */
    private function createUser(
        string $nickname,
        array $roles,
        UserStatus $status,
        string $name = 'Ada',
        ?string $email = null,
        ?int $countryCode = null,
        ?string $cellphone = null,
    ): User {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);

        $existing = $users->findOneByNickname($nickname);

        if ($existing instanceof User) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }

        if (null !== $countryCode && null !== $cellphone) {
            $existingPhone = $users->findOneBy([
                'countryCode' => $countryCode,
                'cellphone' => $cellphone,
            ]);

            if ($existingPhone instanceof User) {
                $entityManager->remove($existingPhone);
                $entityManager->flush();
            }
        }

        $user = new User()
            ->setName($name)
            ->setLastname('Tester')
            ->setNickname($nickname)
            ->setApplicationRoles($roles)
            ->setStatus($status);

        if (null !== $email) {
            $user->setEmail($email);
        }

        if (null !== $countryCode) {
            $user->setCountryCode($countryCode);
        }

        if (null !== $cellphone) {
            $user->setCellphone($cellphone);
        }

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

    private function phone(): string
    {
        return (string) random_int(3000000000, 3999999999);
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

    private function defaultLocale(): string
    {
        /** @var GeneralSettingsProvider $settings */
        $settings = static::getContainer()->get(GeneralSettingsProvider::class);
        /** @var PublicLanguageResolver $languages */
        $languages = static::getContainer()->get(PublicLanguageResolver::class);

        return $languages->defaultLanguage($settings->get())->value;
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'public-avatar-');
        self::assertNotFalse($path);
        $image = imagecreatetruecolor(32, 32);
        self::assertNotFalse($image);
        imagejpeg($image, $path, 90);

        return new UploadedFile($path, 'avatar.jpg', 'image/jpeg', test: true);
    }
}
