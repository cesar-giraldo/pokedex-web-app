<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\GdImageManagerFactory;
use App\Admin\Service\Storage\ImageVariant;
use App\Admin\Service\Storage\ImageVariantGenerationException;
use App\Admin\Service\Storage\ImageVariantProcessor;
use App\Admin\Service\Storage\ImageVariantStore;
use App\Admin\Service\Storage\ObjectStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use const DIRECTORY_SEPARATOR;

#[CoversClass(ImageVariantStore::class)]
#[Group('unit')]
final class ImageVariantStoreTest extends TestCase
{
    private string $tempDirectory = '';

    private ObjectStorage $objectStorage;

    private ImageVariantStore $variantStore;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-variant-store-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->objectStorage = new ObjectStorage(new Filesystem(new LocalFilesystemAdapter($this->tempDirectory)));
        $this->variantStore = new ImageVariantStore(
            $this->objectStorage,
            new ImageVariantProcessor(GdImageManagerFactory::create()),
        );
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testGenerateWritesDerivedVariants(): void
    {
        $originalKey = 'dev/public/pokemon/images/1/photo.jpg';
        $this->objectStorage->write($originalKey, $this->jpegBinary(64, 64));
        $this->variantStore->generate($originalKey, $this->objectStorage->read($originalKey), ImageVariant::pokemonGenerated());

        self::assertTrue($this->objectStorage->fileExists(ImageVariant::Thumb->objectKey($originalKey)));
        self::assertTrue($this->objectStorage->fileExists(ImageVariant::Display->objectKey($originalKey)));
        self::assertSame('image/webp', $this->variantStore->resolveMimeType($originalKey, ImageVariant::Thumb));
    }

    public function testGenerateThrowsWhenProcessorFails(): void
    {
        $originalKey = 'dev/public/pokemon/images/1/photo.jpg';
        $binary = $this->jpegBinary(32, 32);
        $this->objectStorage->write($originalKey, $binary);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Failed to generate image variant.',
            self::callback(static function (array $context): bool {
                return 'thumb' === $context['variant']
                    && isset($context['error']);
            }),
        );

        $store = new ImageVariantStore(
            $this->objectStorage,
            new ImageVariantProcessor(GdImageManagerFactory::create()),
            $logger,
        );

        try {
            $store->generate($originalKey, 'not-an-image', [ImageVariant::Thumb]);
            self::fail('Expected ImageVariantGenerationException.');
        } catch (ImageVariantGenerationException $exception) {
            self::assertSame('No se pudo generar la variante "thumb".', $exception->getMessage());
        }

        self::assertTrue($this->objectStorage->fileExists($originalKey));
        self::assertFalse($this->objectStorage->fileExists(ImageVariant::Thumb->objectKey($originalKey)));
    }

    public function testReadStreamThrowsWhenMissingVariantCannotBeGenerated(): void
    {
        $originalKey = 'dev/public/pokemon/images/1/photo.jpg';
        $this->objectStorage->write($originalKey, 'not-an-image');

        $this->expectException(ImageVariantGenerationException::class);
        $this->variantStore->readStream($originalKey, ImageVariant::Thumb);
    }

    public function testReadStreamGeneratesMissingVariant(): void
    {
        $originalKey = 'dev/public/pokemon/images/1/photo.jpg';
        $this->objectStorage->write($originalKey, $this->jpegBinary(64, 64));

        $stream = $this->variantStore->readStream($originalKey, ImageVariant::Thumb);
        try {
            $contents = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        self::assertNotFalse($contents);
        self::assertNotSame('', $contents);
        self::assertTrue($this->objectStorage->fileExists(ImageVariant::Thumb->objectKey($originalKey)));
    }

    public function testDeleteAllRemovesOriginalAndDerivedKeys(): void
    {
        $originalKey = 'dev/public/pokemon/images/1/photo.jpg';
        $this->objectStorage->write($originalKey, $this->jpegBinary(32, 32));
        $this->variantStore->generate(
            $originalKey,
            $this->objectStorage->read($originalKey),
            [ImageVariant::Thumb, ImageVariant::Display, ImageVariant::Avatar],
        );

        $this->variantStore->deleteAll($originalKey);

        self::assertFalse($this->objectStorage->fileExists($originalKey));
        self::assertFalse($this->objectStorage->fileExists(ImageVariant::Thumb->objectKey($originalKey)));
        self::assertFalse($this->objectStorage->fileExists(ImageVariant::Display->objectKey($originalKey)));
        self::assertFalse($this->objectStorage->fileExists(ImageVariant::Avatar->objectKey($originalKey)));
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function jpegBinary(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'variant-store-jpeg-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagejpeg($image, $path, 90);

        $contents = file_get_contents($path);
        unlink($path);
        self::assertNotFalse($contents);

        return $contents;
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
