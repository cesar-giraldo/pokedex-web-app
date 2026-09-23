<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ImageVariant;
use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\PokemonImageStorage;
use App\Admin\Service\Storage\PokemonImageUploadException;
use App\Entity\Pokemon;
use App\Tests\Admin\Support\ImageStorageFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use const DIRECTORY_SEPARATOR;

#[CoversClass(PokemonImageStorage::class)]
#[Group('unit')]
final class PokemonImageStorageTest extends TestCase
{
    private string $tempDirectory = '';

    private ObjectStorage $objectStorage;

    private PokemonImageStorage $pokemonImageStorage;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-pokemon-image-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->objectStorage = new ObjectStorage(new Filesystem(new LocalFilesystemAdapter($this->tempDirectory)));
        $this->pokemonImageStorage = ImageStorageFactory::pokemon($this->objectStorage);
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testUploadStoresOriginalAndGeneratedVariants(): void
    {
        $pokemon = $this->createPokemon(12);
        $objectKey = $this->pokemonImageStorage->upload($pokemon, $this->createUploadedFile());

        self::assertMatchesRegularExpression('#^dev/public/pokemon/images/12/[a-f0-9]{32}\.jpg$#', $objectKey);
        self::assertTrue($this->objectStorage->fileExists($objectKey));
        self::assertTrue($this->objectStorage->fileExists(ImageVariant::Thumb->objectKey($objectKey)));
        self::assertTrue($this->objectStorage->fileExists(ImageVariant::Display->objectKey($objectKey)));

        $stream = $this->pokemonImageStorage->readStream($objectKey);
        try {
            self::assertNotSame('', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testReadStreamGeneratesMissingThumbVariant(): void
    {
        $pokemon = $this->createPokemon(9);
        $objectKey = $this->pokemonImageStorage->upload($pokemon, $this->createUploadedFile());
        $this->objectStorage->delete(ImageVariant::Thumb->objectKey($objectKey));

        $stream = $this->pokemonImageStorage->readStream($objectKey, ImageVariant::Thumb);
        try {
            self::assertNotSame('', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }

        self::assertTrue($this->objectStorage->fileExists(ImageVariant::Thumb->objectKey($objectKey)));
        self::assertSame('image/webp', $this->pokemonImageStorage->resolveMimeType($objectKey, ImageVariant::Thumb));
    }

    public function testUploadRequiresPersistedPokemon(): void
    {
        $this->expectException(PokemonImageUploadException::class);

        $this->pokemonImageStorage->upload(new Pokemon(), $this->createUploadedFile());
    }

    public function testWriteRollsBackWhenVariantGenerationFails(): void
    {
        $written = [];
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('writeStream')->willReturnCallback(static function (string $path) use (&$written): void {
            $written[$path] = true;
        });
        $filesystem->method('write')->willReturnCallback(static function (string $path): void {
            throw UnableToWriteFile::atLocation($path);
        });
        $filesystem->method('fileExists')->willReturnCallback(static function (string $path) use (&$written): bool {
            return isset($written[$path]);
        });
        $filesystem->method('delete')->willReturnCallback(static function (string $path) use (&$written): void {
            unset($written[$path]);
        });

        $storage = ImageStorageFactory::pokemon(new ObjectStorage($filesystem));

        try {
            $storage->upload($this->createPokemon(12), $this->createUploadedFile());
            self::fail('Expected PokemonImageUploadException.');
        } catch (PokemonImageUploadException $exception) {
            self::assertSame(
                'No se pudieron generar las versiones de la imagen. Inténtalo de nuevo.',
                $exception->getMessage(),
            );
        }

        self::assertSame([], $written);
    }

    public function testDeleteRemovesOriginalAndVariants(): void
    {
        $pokemon = $this->createPokemon(3);
        $objectKey = $this->pokemonImageStorage->upload($pokemon, $this->createUploadedFile());
        $thumbKey = ImageVariant::Thumb->objectKey($objectKey);
        $displayKey = ImageVariant::Display->objectKey($objectKey);

        $this->pokemonImageStorage->delete($objectKey);

        self::assertFalse($this->objectStorage->fileExists($objectKey));
        self::assertFalse($this->objectStorage->fileExists($thumbKey));
        self::assertFalse($this->objectStorage->fileExists($displayKey));

        $this->expectException(RuntimeException::class);
        $this->pokemonImageStorage->readStream($objectKey);
    }

    private function createPokemon(int $id): Pokemon
    {
        $pokemon = new Pokemon()
            ->setName('Pikachu')
            ->setHeight(4)
            ->setWeight(60);

        $reflection = new ReflectionProperty(Pokemon::class, 'id');
        $reflection->setValue($pokemon, $id);

        return $pokemon;
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pokemon-image-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor(32, 32);
        self::assertNotFalse($image);
        imagejpeg($image, $path, 90);

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
