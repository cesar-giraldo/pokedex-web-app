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
    public function testResolveExtensionFromDetectedMimeType(): void
    {
        $file = $this->createUploadedFile('avatar.jpeg', 'application/octet-stream', $this->jpegBytes());

        self::assertSame('image/jpeg', $file->getMimeType());
        self::assertSame('jpg', AllowedImageTypes::resolveExtension($file));
    }

    public function testResolveExtensionIgnoresClientExtensionWhenMimeIsNotAllowed(): void
    {
        $file = $this->createUploadedFile('avatar.jpg', 'image/jpeg', 'not-an-image');

        self::assertNull(AllowedImageTypes::resolveExtension($file));
    }

    public function testRejectsUnsupportedType(): void
    {
        $file = $this->createUploadedFile('notes.txt', 'text/plain', 'fake');

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

    private function createUploadedFile(string $originalName, string $mimeType, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'allowed-image-');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $originalName, $mimeType, test: true);
    }

    private function jpegBytes(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'allowed-jpeg-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor(16, 16);
        self::assertNotFalse($image);
        imagejpeg($image, $path);

        $contents = file_get_contents($path);
        unlink($path);
        self::assertNotFalse($contents);

        return $contents;
    }
}
