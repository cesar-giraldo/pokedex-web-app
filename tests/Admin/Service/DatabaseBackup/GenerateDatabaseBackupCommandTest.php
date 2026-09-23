<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\DatabaseBackup;

use App\Admin\Command\CronJobs\GenerateDatabaseBackupCommand;
use App\Admin\Service\DatabaseBackup\DatabaseBackupExporterInterface;
use App\Admin\Service\DatabaseBackup\DatabaseBackupExportException;
use App\Admin\Service\DatabaseBackup\DatabaseBackupFilenameFactory;
use App\Admin\Service\DatabaseBackup\DatabaseBackupObjectKeyBuilder;
use App\Admin\Service\DatabaseBackup\DatabaseBackupUploaderInterface;
use App\Repository\GeneralSettingsRepository;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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

    private function createCommand(
        DatabaseBackupExporterInterface $exporter,
        DatabaseBackupUploaderInterface $uploader,
        GeneralSettingsRepository $repository,
        string $cacheDir,
    ): GenerateDatabaseBackupCommand {
        return new GenerateDatabaseBackupCommand(
            new DatabaseBackupFilenameFactory(),
            new DatabaseBackupObjectKeyBuilder('test'),
            $exporter,
            $uploader,
            $repository,
            $cacheDir,
        );
    }

    private function createCacheDir(): string
    {
        $directory = sys_get_temp_dir() . '/pokedex-backup-cmd-' . bin2hex(random_bytes(6));
        mkdir($directory, 0o777, true);

        return $directory;
    }
}
