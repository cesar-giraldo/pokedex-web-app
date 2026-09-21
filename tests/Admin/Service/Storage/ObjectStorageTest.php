<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\ObjectStorageException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function is_string;

use const DIRECTORY_SEPARATOR;

#[CoversClass(ObjectStorage::class)]
#[Group('unit')]
final class ObjectStorageTest extends TestCase
{
    private string $tempDirectory = '';

    private ObjectStorage $objectStorage;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-object-storage-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->objectStorage = new ObjectStorage(
            new Filesystem(new LocalFilesystemAdapter($this->tempDirectory)),
        );
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testWriteAndReadUploadedFile(): void
    {
        $objectKey = 'dev/public/pokemon/images/1/file.jpg';
        $this->objectStorage->writeUploadedFile($objectKey, $this->createUploadedFile());

        self::assertTrue($this->objectStorage->fileExists($objectKey));

        $stream = $this->objectStorage->readStream($objectKey);
        try {
            self::assertNotSame('', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testDeleteRemovesExistingObject(): void
    {
        $objectKey = 'dev/public/pokemon/images/1/file.jpg';
        $this->objectStorage->writeUploadedFile($objectKey, $this->createUploadedFile());
        $this->objectStorage->delete($objectKey);

        self::assertFalse($this->objectStorage->fileExists($objectKey));
        $this->expectException(ObjectStorageException::class);
        $this->objectStorage->readStream($objectKey);
    }

    public function testDeleteIgnoresMissingPath(): void
    {
        $this->expectNotToPerformAssertions();

        $this->objectStorage->delete(null);
        $this->objectStorage->delete('');
        $this->objectStorage->delete('missing.jpg');
    }

    public function testDeletePropagatesFilesystemFailures(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('fileExists')->willReturn(true);
        $filesystem->method('delete')->willThrowException(UnableToDeleteFile::atLocation('file.jpg'));

        $objectStorage = new ObjectStorage($filesystem);

        $this->expectException(ObjectStorageException::class);
        $objectStorage->delete('file.jpg');
    }

    public function testTryDeleteLogsFilesystemFailuresWithoutThrowing(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('fileExists')->willReturn(true);
        $filesystem->method('delete')->willThrowException(UnableToDeleteFile::atLocation('file.jpg'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Failed to delete object from storage.',
            self::callback(static function (array $context): bool {
                return 'file.jpg' === ($context['object_key'] ?? null)
                    && isset($context['error'])
                    && is_string($context['error']);
            }),
        );

        $objectStorage = new ObjectStorage($filesystem, $logger);
        $objectStorage->tryDelete('file.jpg');
    }

    public function testResolveMimeType(): void
    {
        self::assertSame('image/jpeg', $this->objectStorage->resolveMimeType('file.jpg'));
        self::assertSame('image/png', $this->objectStorage->resolveMimeType('file.png'));
    }

    public function testWriteAndReadGeneratedBytes(): void
    {
        $objectKey = 'dev/public/pokemon/images/1/file_thumb.webp';
        $this->objectStorage->write($objectKey, 'webp-bytes');

        self::assertTrue($this->objectStorage->fileExists($objectKey));
        self::assertSame('webp-bytes', $this->objectStorage->read($objectKey));
    }

    public function testReadMissingObjectThrows(): void
    {
        $this->expectException(ObjectStorageException::class);
        $this->objectStorage->read('missing.webp');
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'object-storage-');
        self::assertNotFalse($path);
        file_put_contents($path, 'image-bytes');

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', test: true);
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
