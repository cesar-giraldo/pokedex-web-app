<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\DatabaseBackup;

use App\Admin\Service\DatabaseBackup\DatabaseBackupConnectionParser;
use App\Admin\Service\DatabaseBackup\DatabaseBackupDumpCommandFactory;
use App\Admin\Service\DatabaseBackup\DatabaseBackupExporter;
use App\Admin\Service\DatabaseBackup\DatabaseBackupExportException;
use App\Admin\Service\DatabaseBackup\DatabaseBackupProcessRunnerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function is_file;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(DatabaseBackupExporter::class)]
#[Group('unit')]
final class DatabaseBackupExporterTest extends TestCase
{
    private string $outputPath = '';

    protected function tearDown(): void
    {
        if ('' !== $this->outputPath && is_file($this->outputPath)) {
            unlink($this->outputPath);
        }
    }

    public function testExportsPostgresqlDumpWithoutPuttingPasswordInArgv(): void
    {
        $this->outputPath = sys_get_temp_dir() . '/pokedex-backup-pg-' . bin2hex(random_bytes(6)) . '.sql';

        $runner = $this->createMock(DatabaseBackupProcessRunnerInterface::class);
        $runner->expects(self::once())
            ->method('run')
            ->willReturnCallback(function (array $command, array $env): void {
                self::assertSame('pg_dump', $command[0]);
                self::assertContains('--no-owner', $command);
                self::assertContains('--no-acl', $command);
                self::assertContains('--format=plain', $command);
                self::assertContains('--host=database', $command);
                self::assertContains('--username=app', $command);
                self::assertContains('--dbname=app_pokedex', $command);
                self::assertContains('--file=' . $this->outputPath, $command);
                self::assertSame('secret', $env['PGPASSWORD']);
                file_put_contents($this->outputPath, '-- postgresql dump');
            });

        $this->createExporter('postgresql', 'postgresql://app:secret@database:5432/app_pokedex', $runner)
            ->exportToFile($this->outputPath);

        self::assertFileExists($this->outputPath);
    }

    public function testExportsMysqlDumpWithSingleTransaction(): void
    {
        $this->outputPath = sys_get_temp_dir() . '/pokedex-backup-mysql-' . bin2hex(random_bytes(6)) . '.sql';

        $runner = $this->createMock(DatabaseBackupProcessRunnerInterface::class);
        $runner->expects(self::once())
            ->method('run')
            ->willReturnCallback(function (array $command, array $env): void {
                self::assertSame('mysqldump', $command[0]);
                self::assertContains('--single-transaction', $command);
                self::assertContains('--result-file=' . $this->outputPath, $command);
                self::assertContains('app_pokedex', $command);
                self::assertSame('secret', $env['MYSQL_PWD']);
                file_put_contents($this->outputPath, '-- mysql dump');
            });

        $this->createExporter('mysql', 'mysql://app:secret@database:3306/app_pokedex', $runner)
            ->exportToFile($this->outputPath);
    }

    public function testRejectsUnsupportedEngine(): void
    {
        $runner = $this->createMock(DatabaseBackupProcessRunnerInterface::class);
        $runner->expects(self::never())->method('run');

        $this->expectException(DatabaseBackupExportException::class);

        $this->createExporter('sqlite', 'sqlite://localhost/app', $runner)
            ->exportToFile('/tmp/unused.sql');
    }

    public function testFailsWhenDumpFileIsEmpty(): void
    {
        $this->outputPath = sys_get_temp_dir() . '/pokedex-backup-empty-' . bin2hex(random_bytes(6)) . '.sql';
        file_put_contents($this->outputPath, '');

        $runner = $this->createMock(DatabaseBackupProcessRunnerInterface::class);
        $runner->expects(self::once())->method('run');

        $this->expectException(DatabaseBackupExportException::class);

        $this->createExporter('postgresql', 'postgresql://app:secret@database:5432/app_pokedex', $runner)
            ->exportToFile($this->outputPath);
    }

    private function createExporter(
        string $engine,
        string $databaseUrl,
        DatabaseBackupProcessRunnerInterface $runner,
    ): DatabaseBackupExporter {
        return new DatabaseBackupExporter(
            $engine,
            $databaseUrl,
            new DatabaseBackupConnectionParser(),
            new DatabaseBackupDumpCommandFactory(),
            $runner,
        );
    }
}
