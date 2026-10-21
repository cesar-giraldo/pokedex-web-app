<?php

declare(strict_types=1);

namespace App\Tests\Notification\Email;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\SupportedLocale;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Notification\Email\DatabaseBackupStatusMailer;
use App\Notification\Email\TransactionalEmailSender;
use App\Repository\GeneralSettingsRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function json_encode;

#[CoversClass(DatabaseBackupStatusMailer::class)]
#[Group('unit')]
final class DatabaseBackupStatusMailerTest extends TestCase
{
    private const string SETTINGS_URL = 'https://localhost/admin/settings/general';

    public function testCompletedBackupEmailLinksToSettingsWithoutStorageDetails(): void
    {
        $email = $this->captureEmail(
            NotificationType::DatabaseBackupCompleted,
            $this->developer('Ada@Example.com', SupportedLocale::EnglishUnitedStates, 'Ada'),
        );

        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('email.database_backup.completed.subject|en', $email->getSubject());
        self::assertSame('ada@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Ada', $email->getTo()[0]->getName());
        self::assertSame('en', $email->getLocale());
        self::assertSame('email/database_backup/database_backup_status.html.twig', $email->getHtmlTemplate());
        self::assertSame('email/database_backup/database_backup_status.txt.twig', $email->getTextTemplate());
        self::assertSame([
            'succeeded' => true,
            'settings_url' => self::SETTINGS_URL,
            'recipient_name' => 'Ada',
            'language' => 'en',
        ], $email->getContext());
        self::assertEmailOmitsOperationalDetails($email);
    }

    public function testFailedBackupEmailUsesTheFailureCopy(): void
    {
        $email = $this->captureEmail(
            NotificationType::DatabaseBackupFailed,
            $this->developer('dev@example.com', SupportedLocale::SpanishColombia, ''),
        );

        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('email.database_backup.failed.subject|es', $email->getSubject());
        self::assertSame('developer', $email->getTo()[0]->getName());
        self::assertFalse($email->getContext()['succeeded']);
        self::assertSame(self::SETTINGS_URL, $email->getContext()['settings_url']);
        self::assertEmailOmitsOperationalDetails($email);
    }

    public function testSkipsRecipientWithoutEmail(): void
    {
        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::never())->method('send');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('no tiene email'), ['user_id' => 4]);

        $mailer = $this->mailer($transport, $logger);
        $mailer->notify([$this->developer(null, SupportedLocale::SpanishColombia, 'Ada', 4)], NotificationType::DatabaseBackupCompleted);
    }

    public function testRejectsANotificationTypeThatIsNotABackup(): void
    {
        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::never())->method('send');

        $mailer = $this->mailer($transport, $this->createStub(LoggerInterface::class), false);

        $this->expectException(InvalidArgumentException::class);
        $mailer->notify(
            [$this->developer('dev@example.com', SupportedLocale::SpanishColombia, 'Ada')],
            NotificationType::SystemInfo,
        );
    }

    private function captureEmail(NotificationType $type, User $recipient): TemplatedEmail
    {
        $sent = null;
        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (TemplatedEmail $email) use (&$sent): void {
                $sent = $email;
            });

        $this->mailer($transport, $this->createStub(LoggerInterface::class))->notify([$recipient], $type);
        self::assertInstanceOf(TemplatedEmail::class, $sent);

        return $sent;
    }

    private function mailer(MailerInterface $transport, LoggerInterface $logger, bool $expectUrl = true): DatabaseBackupStatusMailer
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        if ($expectUrl) {
            $urls->expects(self::once())
                ->method('generate')
                ->with('app_backend_general_settings', [], UrlGeneratorInterface::ABSOLUTE_URL)
                ->willReturn(self::SETTINGS_URL);
        } else {
            $urls->expects(self::never())->method('generate');
        }

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string => $id . '|' . $locale,
        );

        $repository = $this->createStub(GeneralSettingsRepository::class);
        $repository->method('findSingleton')->willReturn(
            GeneralSettings::createWithDefaults()
                ->setEnabledLanguages(['es', 'en'])
                ->setWebsiteDefaultLanguage('es'),
        );
        $settings = new GeneralSettingsProvider($repository);

        return new DatabaseBackupStatusMailer(
            new TransactionalEmailSender($transport, 'test', 'backup@example.com', 'Pokedex', []),
            $translator,
            $urls,
            new LegalLanguageResolver(),
            $settings,
            $logger,
        );
    }

    private function developer(?string $email, SupportedLocale $locale, string $name, int $id = 1): User
    {
        $user = new User()
            ->setNickname('developer')
            ->setName($name)
            ->setEmail($email)
            ->setLocale(SupportedLocale::SpanishColombia)
            ->setPreferredLanguage($locale->language());

        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }

    private static function assertEmailOmitsOperationalDetails(TemplatedEmail $email): void
    {
        $encoded = json_encode($email->getContext());
        self::assertIsString($encoded);
        self::assertStringNotContainsString('s3', $encoded);
        self::assertStringNotContainsString('object_key', $encoded);
        self::assertStringNotContainsString('exception', $encoded);
        self::assertArrayNotHasKey('message', $email->getContext());
        self::assertFalse(array_key_exists('object_key', $email->getContext()));
    }
}
