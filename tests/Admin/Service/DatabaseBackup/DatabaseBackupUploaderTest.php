<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\DatabaseBackup;

use App\Admin\Service\DatabaseBackup\DatabaseBackupExportException;
use App\Admin\Service\DatabaseBackup\DatabaseBackupUploader;
use App\Admin\Service\Storage\ObjectStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(DatabaseBackupUploader::class)]
#[Group('unit')]
final class DatabaseBackupUploaderTest extends TestCase
{
    private string $tempDirectory = '';

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-backup-upload-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testUploadsLocalFileToObjectStorage(): void
    {
        $localPath = $this->tempDirectory . '/source.sql';
        file_put_contents($localPath, '-- dump');

        $objectStorage = new ObjectStorage(
            new Filesystem(new LocalFilesystemAdapter($this->tempDirectory . '/s3')),
        );

        new DatabaseBackupUploader($objectStorage)->upload(
            'test/private/database-backups/backup.sql',
            $localPath,
        );

        self::assertSame(
            '-- dump',
            $objectStorage->read('test/private/database-backups/backup.sql'),
        );
    }

    public function testWrapsStorageFailures(): void
    {
        $localPath = $this->tempDirectory . '/source.sql';
        file_put_contents($localPath, '-- dump');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('writeStream')->willThrowException(UnableToWriteFile::atLocation('key.sql'));

        $this->expectException(DatabaseBackupExportException::class);

        new DatabaseBackupUploader(new ObjectStorage($filesystem))->upload('key.sql', $localPath);
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
