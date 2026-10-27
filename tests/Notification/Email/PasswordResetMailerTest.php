<?php

declare(strict_types=1);

namespace App\Tests\Notification\Email;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\PasswordResetChannel;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\SupportedLocale;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Notification\Email\PasswordResetCompletedMailer;
use App\Notification\Email\PasswordResetLanguage;
use App\Notification\Email\PasswordResetRequestMailer;
use App\Notification\Email\TransactionalEmailSender;
use App\Repository\GeneralSettingsRepository;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;

#[CoversClass(PasswordResetRequestMailer::class)]
#[CoversClass(PasswordResetCompletedMailer::class)]
#[CoversClass(PasswordResetLanguage::class)]
#[Group('unit')]
final class PasswordResetMailerTest extends TestCase
{
    public function testRequestEmailUsesThePreferredLanguageAndDoesNotIncludeAPassword(): void
    {
        $user = $this->user(SupportedLanguage::English);
        $email = $this->captureRequest($user, PasswordResetChannel::Web, SupportedLanguage::Spanish);

        self::assertSame('email.password_reset.subject|en', $email->getSubject());
        self::assertSame('ada@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Ada Tester', $email->getTo()[0]->getName());
        self::assertSame('en', $email->getLocale());
        self::assertSame('https://localhost/en/reset-password/selector.verifier', $email->getContext()['reset_url']);
        self::assertArrayNotHasKey('password', $email->getContext());
        self::assertStringNotContainsString('Secret', (string) json_encode($email->getContext()));
    }

    public function testRequestEmailFallsBackToTheRequestLanguageAndUsesTheAdminPath(): void
    {
        $user = $this->user(SupportedLanguage::French);
        $email = $this->captureRequest($user, PasswordResetChannel::Admin, SupportedLanguage::Portuguese);

        self::assertSame('email.password_reset.subject|pt', $email->getSubject());
        self::assertSame('https://localhost/admin/reset-password/selector.verifier', $email->getContext()['reset_url']);
    }

    public function testRequestEmailIsSkippedWhenTheAccountHasNoAddress(): void
    {
        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::never())->method('send');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $mailer = $this->requestMailer($transport, $logger);

        $mailer->send($this->user(SupportedLanguage::Spanish, null), 'selector.verifier', PasswordResetChannel::Web, SupportedLanguage::Spanish);
    }

    public function testCompletedEmailLinksToContactOnTheWebAndToTheContactAddressInAdmin(): void
    {
        $user = $this->user(SupportedLanguage::Spanish);
        $web = $this->captureCompleted($user, PasswordResetChannel::Web, SupportedLanguage::Spanish);
        $admin = $this->captureCompleted($user, PasswordResetChannel::Admin, SupportedLanguage::Spanish);

        self::assertSame('email.password_reset_completed.subject|es', $web->getSubject());
        self::assertSame('web', $web->getContext()['channel']);
        self::assertSame('https://localhost/es/contact', $web->getContext()['contact_url']);
        self::assertSame('contact.email|es', $web->getContext()['contact_email']);
        self::assertIsString($web->getContext()['changed_at']);
        self::assertNotSame('', $web->getContext()['changed_at']);
        self::assertNull($admin->getContext()['contact_url']);
        self::assertSame('admin', $admin->getContext()['channel']);
        self::assertArrayNotHasKey('password', $web->getContext());
        self::assertArrayNotHasKey('password', $admin->getContext());
    }

    private function captureRequest(User $user, PasswordResetChannel $channel, SupportedLanguage $requestLanguage): TemplatedEmail
    {
        $sent = null;
        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::once())->method('send')->willReturnCallback(
            static function (TemplatedEmail $email) use (&$sent): void {
                $sent = $email;
            },
        );
        $this->requestMailer($transport, $this->createStub(LoggerInterface::class))->send(
            $user,
            'selector.verifier',
            $channel,
            $requestLanguage,
        );
        self::assertInstanceOf(TemplatedEmail::class, $sent);

        return $sent;
    }

    private function captureCompleted(User $user, PasswordResetChannel $channel, SupportedLanguage $requestLanguage): TemplatedEmail
    {
        $sent = null;
        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::once())->method('send')->willReturnCallback(
            static function (TemplatedEmail $email) use (&$sent): void {
                $sent = $email;
            },
        );
        $this->completedMailer($transport)->send($user, $channel, new DateTime('2026-10-27 16:30:00'), $requestLanguage);
        self::assertInstanceOf(TemplatedEmail::class, $sent);

        return $sent;
    }

    private function requestMailer(MailerInterface $transport, LoggerInterface $logger): PasswordResetRequestMailer
    {
        return new PasswordResetRequestMailer(
            new TransactionalEmailSender($transport, 'test', 'reset@example.com', 'Pokedex', []),
            $this->translator(),
            $this->urls(),
            $this->language(),
            $logger,
        );
    }

    private function completedMailer(MailerInterface $transport): PasswordResetCompletedMailer
    {
        return new PasswordResetCompletedMailer(
            new TransactionalEmailSender($transport, 'test', 'reset@example.com', 'Pokedex', []),
            $this->translator(),
            $this->urls(),
            $this->language(),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function language(): PasswordResetLanguage
    {
        $repository = $this->createStub(GeneralSettingsRepository::class);
        $repository->method('findSingleton')->willReturn(
            GeneralSettings::createWithDefaults()
                ->setEnabledLanguages(['es', 'en', 'pt'])
                ->setWebsiteDefaultLanguage('es'),
        );

        return new PasswordResetLanguage(new LegalLanguageResolver(), new GeneralSettingsProvider($repository));
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string => $id . '|' . $locale,
        );

        return $translator;
    }

    private function urls(): UrlGeneratorInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = []): string {
                $locale = $parameters['_locale'] ?? '';
                $locale = is_string($locale) ? $locale : '';

                return match ($route) {
                    'app_public_reset_password' => 'https://localhost/' . $locale . '/reset-password/' . $parameters['token'],
                    'app_admin_reset_password' => 'https://localhost/admin/reset-password/' . $parameters['token'],
                    'app_public_contact' => 'https://localhost/' . $locale . '/contact',
                    default => 'https://localhost/',
                };
            },
        );

        return $urls;
    }

    private function user(?SupportedLanguage $preferred, ?string $email = 'ada@example.com'): User
    {
        $user = new User()
            ->setNickname('ada')
            ->setName('Ada')
            ->setLastname('Tester')
            ->setEmail($email)
            ->setLocale(SupportedLocale::SpanishColombia)
            ->setPreferredLanguage($preferred);
        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, 3);

        return $user;
    }
}
