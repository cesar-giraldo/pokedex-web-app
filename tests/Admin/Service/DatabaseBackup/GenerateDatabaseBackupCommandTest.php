<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\DatabaseBackup;

use App\Admin\Command\CronJobs\GenerateDatabaseBackupCommand;
use App\Admin\Service\DatabaseBackup\DatabaseBackupExporterInterface;
use App\Admin\Service\DatabaseBackup\DatabaseBackupExportException;
use App\Admin\Service\DatabaseBackup\DatabaseBackupFilenameFactory;
use App\Admin\Service\DatabaseBackup\DatabaseBackupObjectKeyBuilder;
use App\Admin\Service\DatabaseBackup\DatabaseBackupUploaderInterface;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Notification;
use App\Entity\User;
use App\Notification\Email\DatabaseBackupStatusMailer;
use App\Notification\Email\TransactionalEmailSender;
use App\Notification\NotificationService;
use App\Repository\GeneralSettingsRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function str_ends_with;
use function sys_get_temp_dir;

#[CoversClass(GenerateDatabaseBackupCommand::class)]
#[Group('unit')]
final class GenerateDatabaseBackupCommandTest extends TestCase
{
    public function testUploadsDumpAndRecordsMetadata(): void
    {
        $cacheDir = $this->createCacheDir();

        $exporter = $this->createMock(DatabaseBackupExporterInterface::class);
        $exporter->expects(self::once())
            ->method('exportToFile')
            ->willReturnCallback(static function (string $path): void {
                file_put_contents($path, '-- dump');
            });

        $uploader = $this->createMock(DatabaseBackupUploaderInterface::class);
        $uploader->expects(self::once())->method('upload');

        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::once())
            ->method('recordLastDatabaseBackup')
            ->with(
                self::stringContains('/private/database-backups/backup-'),
                self::isInstanceOf(DateTimeInterface::class),
            );

        $tester = new CommandTester($this->createCommand($exporter, $uploader, $repository, $cacheDir));
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Backup almacenado', $tester->getDisplay());
        self::assertStringContainsString('No se notificará el backup: no hay developers activos y visibles.', $tester->getDisplay());
    }

    public function testDoesNotRecordMetadataWhenExportFails(): void
    {
        $exporter = $this->createMock(DatabaseBackupExporterInterface::class);
        $exporter->expects(self::once())
            ->method('exportToFile')
            ->willThrowException(new DatabaseBackupExportException('dump failed'));

        $uploader = $this->createMock(DatabaseBackupUploaderInterface::class);
        $uploader->expects(self::never())->method('upload');

        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::never())->method('recordLastDatabaseBackup');

        $tester = new CommandTester($this->createCommand(
            $exporter,
            $uploader,
            $repository,
            $this->createCacheDir(),
        ));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('dump failed', $tester->getDisplay());
    }

    public function testReturnsFailureWhenMetadataUpdateFailsAfterUpload(): void
    {
        $exporter = $this->createMock(DatabaseBackupExporterInterface::class);
        $exporter->expects(self::once())
            ->method('exportToFile')
            ->willReturnCallback(static function (string $path): void {
                file_put_contents($path, '-- dump');
            });

        $uploader = $this->createMock(DatabaseBackupUploaderInterface::class);
        $uploader->expects(self::once())->method('upload');

        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::once())
            ->method('recordLastDatabaseBackup')
            ->willThrowException(new RuntimeException('db write failed'));

        $tester = new CommandTester($this->createCommand(
            $exporter,
            $uploader,
            $repository,
            $this->createCacheDir(),
        ));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('se subió a S3', $tester->getDisplay());
    }

    public function testReturnsFailureWhenLockDirectoryCannotBeCreated(): void
    {
        $cacheFile = sys_get_temp_dir() . '/pokedex-backup-not-a-dir-' . bin2hex(random_bytes(6));
        file_put_contents($cacheFile, 'not a directory');

        $exporter = $this->createMock(DatabaseBackupExporterInterface::class);
        $exporter->expects(self::never())->method('exportToFile');

        $uploader = $this->createMock(DatabaseBackupUploaderInterface::class);
        $uploader->expects(self::never())->method('upload');

        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::never())->method('recordLastDatabaseBackup');

        $tester = new CommandTester($this->createCommand(
            $exporter,
            $uploader,
            $repository,
            $cacheFile,
        ));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('directorio de bloqueo', $tester->getDisplay());
    }

    public function testNotifiesDevelopersWhenBackupSucceeds(): void
    {
        $uploader = $this->createMock(DatabaseBackupUploaderInterface::class);
        $uploader->expects($this->once())->method('upload');
        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects($this->once())->method('recordLastDatabaseBackup');

        $notification = $this->captureNotification($this->successfulExporter(), $uploader, $repository, true);

        self::assertSame(NotificationType::DatabaseBackupCompleted, $notification->getType());
        self::assertSame('Backup de base de datos completado', $notification->getTitle());
        self::assertStringContainsString('se subió a S3', $notification->getMessage());
        self::assertSame('/admin/settings/general', $notification->getActionUrl());
    }

    public function testNotifiesDevelopersWhenExportFails(): void
    {
        $exporter = $this->createStub(DatabaseBackupExporterInterface::class);
        $exporter->method('exportToFile')->willThrowException(new DatabaseBackupExportException('dump failed'));

        $uploader = $this->createMock(DatabaseBackupUploaderInterface::class);
        $uploader->expects($this->never())->method('upload');
        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects($this->never())->method('recordLastDatabaseBackup');

        $notification = $this->captureNotification($exporter, $uploader, $repository, false);

        self::assertSame(NotificationType::DatabaseBackupFailed, $notification->getType());
        self::assertSame('Falló el backup de base de datos', $notification->getTitle());
        self::assertSame('dump failed', $notification->getMessage());
    }

    public function testBackupSucceedsWhenStatusEmailFails(): void
    {
        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::once())->method('send')->willThrowException(new RuntimeException('ses down'));

        $notification = $this->captureNotification(
            $this->successfulExporter(),
            $this->uploaderExpectingUpload(),
            $this->repositoryExpectingMetadata(),
            true,
            $transport,
        );

        self::assertSame(NotificationType::DatabaseBackupCompleted, $notification->getType());
    }

    public function testSendsStatusEmailWhenInAppNotificationFails(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willThrowException(new RuntimeException('db down'));

        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())
            ->method('findByRoles')
            ->with([UserRole::Developer], UserStatus::Active, true)
            ->willReturn([$this->developer()]);

        $transport = $this->createMock(MailerInterface::class);
        $transport->expects(self::once())->method('send');

        $tester = new CommandTester($this->createCommand(
            $this->successfulExporter(),
            $this->uploaderExpectingUpload(),
            $this->repositoryExpectingMetadata(),
            $this->createCacheDir(),
            new NotificationService($entityManager, $this->createStub(NotificationRepository::class)),
            $users,
            $this->relativeUrlGenerator(),
            $this->statusMailer($transport),
        ));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Backup almacenado', $tester->getDisplay());
        self::assertStringContainsString('Notificando el backup a 1 usuario: Ada.', $tester->getDisplay());
    }

    private function captureNotification(
        DatabaseBackupExporterInterface $exporter,
        DatabaseBackupUploaderInterface $uploader,
        GeneralSettingsRepository $repository,
        bool $expectSuccess,
        ?MailerInterface $mailer = null,
    ): Notification {
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });
        $entityManager->expects(self::once())->method('flush');

        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())
            ->method('findByRoles')
            ->with([UserRole::Developer], UserStatus::Active, true)
            ->willReturn([$this->developer()]);

        if (null === $mailer) {
            $transport = $this->createMock(MailerInterface::class);
            $transport->expects(self::once())
                ->method('send')
                ->with(self::callback(static function (Email $email) use ($expectSuccess): bool {
                    if (!$email instanceof TemplatedEmail) {
                        return false;
                    }

                    $status = $expectSuccess ? 'completed' : 'failed';
                    $context = $email->getContext();

                    return str_ends_with((string) $email->getSubject(), $status . '.subject|en')
                        && ($context['succeeded'] ?? null) === $expectSuccess
                        && 'https://localhost/admin/settings/general' === ($context['settings_url'] ?? null)
                        && !array_key_exists('object_key', $context);
                }));
        } else {
            $transport = $mailer;
        }

        $tester = new CommandTester($this->createCommand(
            $exporter,
            $uploader,
            $repository,
            $this->createCacheDir(),
            new NotificationService($entityManager, $this->createStub(NotificationRepository::class)),
            $users,
            $this->relativeUrlGenerator(),
            $this->statusMailer($transport),
        ));

        $status = $tester->execute([]);
        self::assertSame($expectSuccess ? Command::SUCCESS : Command::FAILURE, $status);
        self::assertStringContainsString('Notificando el backup a 1 usuario: Ada.', $tester->getDisplay());
        self::assertInstanceOf(Notification::class, $persisted);

        return $persisted;
    }

    private function successfulExporter(): DatabaseBackupExporterInterface
    {
        $exporter = $this->createStub(DatabaseBackupExporterInterface::class);
        $exporter->method('exportToFile')->willReturnCallback(static function (string $path): void {
            file_put_contents($path, '-- dump');
        });

        return $exporter;
    }

    private function developer(): User
    {
        $user = new User()
            ->setNickname('developer')
            ->setName('Ada')
            ->setEmail('Ada@Example.com')
            ->setLocale(SupportedLocale::EnglishUnitedStates)
            ->setStatus(UserStatus::Active)
            ->setIsHidden(false);
        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, 1);

        return $user;
    }

    private function createCommand(
        DatabaseBackupExporterInterface $exporter,
        DatabaseBackupUploaderInterface $uploader,
        GeneralSettingsRepository $repository,
        string $cacheDir,
        ?NotificationService $notificationService = null,
        ?UserRepository $userRepository = null,
        ?UrlGeneratorInterface $urlGenerator = null,
        ?DatabaseBackupStatusMailer $statusMailer = null,
    ): GenerateDatabaseBackupCommand {
        if (null === $statusMailer) {
            $transport = $this->createMock(MailerInterface::class);
            $transport->expects(self::never())->method('send');
            $statusMailer = $this->statusMailer($transport);
        }

        return new GenerateDatabaseBackupCommand(
            new DatabaseBackupFilenameFactory(),
            new DatabaseBackupObjectKeyBuilder('test'),
            $exporter,
            $uploader,
            $repository,
            $cacheDir,
            $notificationService ?? new NotificationService(
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(NotificationRepository::class),
            ),
            $userRepository ?? $this->createStub(UserRepository::class),
            $urlGenerator ?? $this->createStub(UrlGeneratorInterface::class),
            $statusMailer,
        );
    }

    private function statusMailer(MailerInterface $transport): DatabaseBackupStatusMailer
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://localhost/admin/settings/general');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string => $id . '|' . $locale,
        );

        return new DatabaseBackupStatusMailer(
            new TransactionalEmailSender($transport, 'test', 'backup@example.com', 'Pokedex', []),
            $translator,
            $urls,
        );
    }

    private function relativeUrlGenerator(): UrlGeneratorInterface
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())
            ->method('generate')
            ->with('app_backend_general_settings')
            ->willReturn('/admin/settings/general');

        return $urls;
    }

    private function uploaderExpectingUpload(): DatabaseBackupUploaderInterface
    {
        $uploader = $this->createMock(DatabaseBackupUploaderInterface::class);
        $uploader->expects(self::once())->method('upload');

        return $uploader;
    }

    private function repositoryExpectingMetadata(): GeneralSettingsRepository
    {
        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::once())->method('recordLastDatabaseBackup');

        return $repository;
    }

    private function createCacheDir(): string
    {
        $directory = sys_get_temp_dir() . '/pokedex-backup-cmd-' . bin2hex(random_bytes(6));
        mkdir($directory, 0o777, true);

        return $directory;
    }
}
