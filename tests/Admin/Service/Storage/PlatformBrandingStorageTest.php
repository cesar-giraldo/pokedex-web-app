<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\PlatformBrandingAsset;
use App\Admin\Service\Storage\PlatformBrandingStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function fclose;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function stream_get_contents;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(PlatformBrandingStorage::class)]
#[Group('unit')]
final class PlatformBrandingStorageTest extends TestCase
{
    private string $tempDirectory = '';

    private PlatformBrandingStorage $storage;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-branding-storage-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->storage = new PlatformBrandingStorage(
            new ObjectStorage(new Filesystem(new LocalFilesystemAdapter($this->tempDirectory))),
            'dev',
        );
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testAllocateObjectKeyIsScopedToTheAsset(): void
    {
        $objectKey = $this->storage->allocateObjectKey(PlatformBrandingAsset::Logo);

        self::assertTrue($this->storage->isAllowedKey(PlatformBrandingAsset::Logo, $objectKey));
        self::assertFalse($this->storage->isAllowedKey(PlatformBrandingAsset::LogoDark, $objectKey));
        self::assertFalse($this->storage->isAllowedKey(
            PlatformBrandingAsset::Logo,
            'dev/private/database-backups/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.svg',
        ));
        self::assertFalse($this->storage->isAllowedKey(
            PlatformBrandingAsset::Logo,
            'dev/private/settings/branding/platform-logo/../secret.svg',
        ));
    }

    public function testWritesAndReadsSvgBytes(): void
    {
        $objectKey = $this->storage->allocateObjectKey(PlatformBrandingAsset::Icon);
        $this->storage->write($objectKey, $this->createUploadedFile());

        $stream = $this->storage->readStream($objectKey);
        $contents = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($contents);
        self::assertStringContainsString('<svg', $contents);
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = $this->tempDirectory . '/upload.svg';
        self::assertNotFalse(file_put_contents(
            $path,
            '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        ));

        return new UploadedFile($path, 'icon.svg', 'image/svg+xml', test: true);
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
