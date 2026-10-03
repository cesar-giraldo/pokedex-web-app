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
use App\Entity\Enum\UserRole;
use App\Entity\Notification;
use App\Entity\User;
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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
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

    private function captureNotification(
        DatabaseBackupExporterInterface $exporter,
        DatabaseBackupUploaderInterface $uploader,
        GeneralSettingsRepository $repository,
        bool $expectSuccess,
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
            ->with([UserRole::Developer])
            ->willReturn([$this->developer()]);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())
            ->method('generate')
            ->with('app_backend_general_settings')
            ->willReturn('/admin/settings/general');

        $tester = new CommandTester($this->createCommand(
            $exporter,
            $uploader,
            $repository,
            $this->createCacheDir(),
            new NotificationService($entityManager, $this->createStub(NotificationRepository::class)),
            $users,
            $urls,
        ));

        $status = $tester->execute([]);
        self::assertSame($expectSuccess ? Command::SUCCESS : Command::FAILURE, $status);
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
        $user = new User()->setNickname('developer');
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
    ): GenerateDatabaseBackupCommand {
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
        );
    }

    private function createCacheDir(): string
    {
        $directory = sys_get_temp_dir() . '/pokedex-backup-cmd-' . bin2hex(random_bytes(6));
        mkdir($directory, 0o777, true);

        return $directory;
    }
}
