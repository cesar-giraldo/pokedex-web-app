<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\LegalAcceptanceMethod;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Repository\UserRepository;
use App\Tests\Double\CapturingConfirmationCodeSender;
use App\Web\Service\PublicLanguageResolver;
use App\Web\Service\SignupLegalCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

use function bin2hex;
use function count;
use function random_bytes;
use function sprintf;

#[Group('functional')]
final class PublicRegistrationTest extends WebTestCase
{
    private const string PASSWORD = 'Secret123';

    /** @var list<string> */
    private array $nicknames = [];

    /** @var list<string> */
    private array $emails = [];

    protected function tearDown(): void
    {
        CapturingConfirmationCodeSender::$lastCode = null;

        if (([] !== $this->nicknames || [] !== $this->emails) && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);

            foreach ($this->emails as $email) {
                $entityManager->createQueryBuilder()
                    ->delete(Notification::class, 'notification')
                    ->andWhere('notification.message LIKE :email')
                    ->setParameter('email', '%' . $email . '%')
                    ->getQuery()
                    ->execute();
            }

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

    public function testRegisterConfirmsTheAccountAndNotifiesActiveStaff(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $email = $this->email('new');
        $admin = $this->staff('admin', [UserRole::Admin], false);
        $hidden = $this->staff('hidden', [UserRole::Admin], true);
        $developer = $this->staff('dev', [UserRole::Developer], false);
        /** @var SignupLegalCatalog $catalog */
        $catalog = static::getContainer()->get(SignupLegalCatalog::class);
        $documents = $catalog->available(SupportedLanguage::from($locale));

        $crawler = $client->request('GET', '/' . $locale . '/register');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#register-lastname');
        self::assertStringNotContainsString($this->trans($locale, 'auth.unavailable'), (string) $client->getResponse()->getContent());

        if ([] === $documents) {
            self::assertSelectorNotExists('#register-terms');
        } else {
            self::assertSelectorExists('#register-terms');
        }

        $form = $crawler->filter('form')->form();
        $this->fillRegistration($form, $email);
        $client->submit($form);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringEndsWith('/' . $locale . '/account/confirm', $location);
        $user = $this->reload($email);
        self::assertSame(UserStatus::UnconfirmedAccount, $user->getStatus());
        self::assertSame($email, $user->getNickname());
        self::assertSame([UserRole::User], $user->getApplicationRoles());
        self::assertSame($locale, $user->getPreferredLanguage()?->value);
        self::assertSame('America/Mexico_City', $user->getTimezone());
        self::assertSame(SupportedLocale::SpanishMexico, $user->getLocale());
        self::assertSame(TimeFormat::Hour24, $user->getTimeFormat());
        self::assertCount(count($documents), $this->acceptances($user));

        foreach ($this->acceptances($user) as $acceptance) {
            self::assertSame(LegalAcceptanceMethod::Signup, $acceptance->getMethod());
        }

        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(6, '[data-confirmation-code-target="digit"]');
        $code = CapturingConfirmationCodeSender::$lastCode;
        self::assertNotNull($code);

        $client->request('GET', '/' . $locale);
        $this->assertRedirectEndsWith($client, '/' . $locale . '/account/confirm');
        $client->request('GET', '/' . $locale . '/profile');
        $this->assertRedirectEndsWith($client, '/' . $locale . '/account/confirm');

        $crawler = $client->request('GET', '/' . $locale . '/account/confirm');
        $wrong = '000000' === $code ? '111111' : '000000';
        $this->submitCode($client, $crawler, $wrong);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $this->trans($locale, 'auth.confirm_mismatch'));
        self::assertSame(UserStatus::UnconfirmedAccount, $this->reload($email)->getStatus());

        $crawler = $client->request('GET', '/' . $locale . '/account/confirm');
        $this->submitCode($client, $crawler, $code);
        $this->assertRedirectEndsWith($client, '/' . $locale . '/profile');
        self::assertSame(UserStatus::UncompleteProfileInfo, $this->reload($email)->getStatus());
        $client->followRedirect();
        self::assertSelectorCount(1, 'main .alert-warning');
        self::assertSelectorTextContains('main .alert-warning', $this->trans($locale, 'account.confirmed_complete_profile'));

        self::assertNotNull($this->notice($admin, $email));
        self::assertNotNull($this->notice($developer, $email));
        self::assertNull($this->notice($hidden, $email));
    }

    public function testDuplicateEmailUsesAGenericMessage(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $email = $this->email('dup');
        $crawler = $client->request('GET', '/' . $locale . '/register');
        $form = $crawler->filter('form')->form();
        $this->fillRegistration($form, $email);
        $client->submit($form);
        self::assertResponseRedirects();

        static::ensureKernelShutdown();
        $other = static::createClient();
        $crawler = $other->request('GET', '/' . $locale . '/register');
        $form = $crawler->filter('form')->form();
        $this->fillRegistration($form, $email);
        $other->submit($form);

        self::assertResponseIsSuccessful();
        $expected = $this->trans($locale, 'auth.register_unavailable', 'validators');
        self::assertSelectorTextContains('#register-email-error', $expected);
        self::assertStringNotContainsString('ya está registrado', (string) $other->getResponse()->getContent());
    }

    public function testMismatchedPasswordsWithAnEmptyTimeFormatStayOnTheForm(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $email = $this->email('empty-format');
        $crawler = $client->request('GET', '/' . $locale . '/register');
        $form = $crawler->filter('form')->form();
        $this->fillRegistration($form, $email);
        $form['registration[passwordConfirm]'] = 'Other123';
        $form['registration[timezone]'] = '';
        $form['registration[locale]'] = '';
        $form['registration[timeFormat]'] = '';
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '#register-password-confirm-error',
            $this->trans($locale, 'auth.password_mismatch', 'validators'),
        );
        self::assertNull($this->find($email));
    }

    public function testPasswordMismatchAndAFilledHoneypotDoNotCreateUsers(): void
    {
        $client = static::createClient();
        $locale = $this->defaultLocale();
        $email = $this->email('bad');
        $crawler = $client->request('GET', '/' . $locale . '/register');
        $form = $crawler->filter('form')->form();
        $this->fillRegistration($form, $email);
        $form['registration[passwordConfirm]'] = 'Other123';
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '#register-password-confirm-error',
            $this->trans($locale, 'auth.password_mismatch', 'validators'),
        );
        self::assertNull($this->find($email));

        $crawler = $client->request('GET', '/' . $locale . '/register');
        $form = $crawler->filter('form')->form();
        $this->fillRegistration($form, $email);
        $form['registration[companyFax]'] = 'spam';
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $this->trans($locale, 'auth.register_rejected', 'validators'));
        self::assertNull($this->find($email));
    }

    private function fillRegistration(Form $form, string $email): void
    {
        $form['registration[name]'] = 'Ada';
        $form['registration[lastname]'] = 'Lovelace';
        $form['registration[email]'] = $email;
        $form['registration[password]'] = self::PASSWORD;
        $form['registration[passwordConfirm]'] = self::PASSWORD;
        $form['registration[timezone]'] = 'America/Mexico_City';
        $form['registration[locale]'] = 'es-MX';
        $form['registration[timeFormat]'] = '24h';

        if ($form->has('registration[terms]')) {
            $terms = $form['registration[terms]'];
            self::assertInstanceOf(ChoiceFormField::class, $terms);
            $terms->tick();
        }
    }

    private function submitCode(KernelBrowser $client, Crawler $crawler, string $code): void
    {
        $form = $crawler->filter('form[data-controller="confirmation-code"]')->form();

        foreach (str_split($code) as $index => $digit) {
            $form['confirmation_code[digit' . ($index + 1) . ']'] = $digit;
        }

        $client->submit($form);
    }

    /**
     * @param list<UserRole> $roles
     */
    private function staff(string $prefix, array $roles, bool $hidden): User
    {
        $nickname = $this->remember($prefix . bin2hex(random_bytes(3)));
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User()
            ->setName('Staff')
            ->setLastname('Member')
            ->setNickname($nickname)
            ->setEmail($nickname . '@example.com')
            ->setApplicationRoles($roles)
            ->setStatus(UserStatus::Active)
            ->setIsHidden($hidden);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function email(string $prefix): string
    {
        $email = sprintf('%s-%s@example.com', $prefix, bin2hex(random_bytes(4)));
        $this->emails[] = $email;
        $this->nicknames[] = $email;

        return $email;
    }

    private function remember(string $nickname): string
    {
        $nickname = User::normalizeNickname($nickname);
        $this->nicknames[] = $nickname;

        return $nickname;
    }

    private function reload(string $nickname): User
    {
        $user = $this->find($nickname);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function find(string $nickname): ?User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);

        return $users->findOneByNickname($nickname);
    }

    /**
     * @return list<UserLegalAcceptance>
     */
    private function acceptances(User $user): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var list<UserLegalAcceptance> $acceptances */
        $acceptances = $entityManager->getRepository(UserLegalAcceptance::class)->findBy(['user' => $user]);

        return $acceptances;
    }

    private function notice(User $recipient, string $email): ?Notification
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $recipient = $entityManager->find(User::class, $recipient->getId());
        self::assertInstanceOf(User::class, $recipient);

        /** @var list<Notification> $notifications */
        $notifications = $entityManager->getRepository(Notification::class)->findBy(['recipient' => $recipient]);

        foreach ($notifications as $notification) {
            if (str_contains($notification->getMessage(), $email)) {
                return $notification;
            }
        }

        return null;
    }

    private function assertRedirectEndsWith(KernelBrowser $client, string $path): void
    {
        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertTrue(str_ends_with($location, $path), $location);
    }

    private function defaultLocale(): string
    {
        /** @var GeneralSettingsProvider $settings */
        $settings = static::getContainer()->get(GeneralSettingsProvider::class);
        /** @var PublicLanguageResolver $languages */
        $languages = static::getContainer()->get(PublicLanguageResolver::class);

        return $languages->defaultLanguage($settings->get())->value;
    }

    private function trans(string $locale, string $key, string $domain = 'messages'): string
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);
        /** @var LocaleSwitcher $localeSwitcher */
        $localeSwitcher = static::getContainer()->get(LocaleSwitcher::class);

        return $localeSwitcher->runWithLocale(
            $locale,
            static fn (): string => $translator->trans($key, [], $domain),
        );
    }
}
