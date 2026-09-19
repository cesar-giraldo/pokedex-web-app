<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\AllowedImageTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(AllowedImageTypes::class)]
#[Group('unit')]
final class AllowedImageTypesTest extends TestCase
{
    public function testResolveExtensionFromMimeType(): void
    {
        $file = $this->createUploadedFile('avatar.jpg', 'image/jpeg');

        self::assertSame('jpg', AllowedImageTypes::resolveExtension($file));
    }

    public function testResolveExtensionNormalizesJpeg(): void
    {
        $file = $this->createUploadedFile('avatar.jpeg', 'application/octet-stream');

        self::assertSame('jpg', AllowedImageTypes::resolveExtension($file));
    }

    public function testRejectsUnsupportedType(): void
    {
        $file = $this->createUploadedFile('notes.txt', 'text/plain');

        self::assertNull(AllowedImageTypes::resolveExtension($file));
    }

    public function testMimeTypeFromObjectKey(): void
    {
        self::assertSame('image/jpeg', AllowedImageTypes::mimeTypeFromObjectKey('path/file.jpg'));
        self::assertSame('image/png', AllowedImageTypes::mimeTypeFromObjectKey('path/file.png'));
        self::assertSame('image/webp', AllowedImageTypes::mimeTypeFromObjectKey('path/file.webp'));
        self::assertSame('application/octet-stream', AllowedImageTypes::mimeTypeFromObjectKey('path/file.gif'));
    }

    public function testAllowedExtensionsAndMimeTypes(): void
    {
        self::assertTrue(AllowedImageTypes::isAllowedExtension('jpg'));
        self::assertFalse(AllowedImageTypes::isAllowedExtension('gif'));
        self::assertSame(['image/jpeg', 'image/png', 'image/webp'], AllowedImageTypes::mimeTypes());
    }

    private function createUploadedFile(string $originalName, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'allowed-image-');
        self::assertNotFalse($path);
        file_put_contents($path, 'fake');

        return new UploadedFile($path, $originalName, $mimeType, test: true);
    }
}
