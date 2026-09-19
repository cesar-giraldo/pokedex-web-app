<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\PokemonImageStorage;
use App\Admin\Service\Storage\PokemonImageUploadException;
use App\Entity\Pokemon;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
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

    private PokemonImageStorage $pokemonImageStorage;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-pokemon-image-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->pokemonImageStorage = new PokemonImageStorage(
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

    public function testUploadStoresFileAndReturnsObjectKey(): void
    {
        $pokemon = $this->createPokemon(12);
        $objectKey = $this->pokemonImageStorage->upload($pokemon, $this->createUploadedFile());

        self::assertMatchesRegularExpression('#^dev/public/pokemon/images/12/[a-f0-9]{32}\.jpg$#', $objectKey);

        $stream = $this->pokemonImageStorage->readStream($objectKey);
        try {
            self::assertNotSame('', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testUploadRequiresPersistedPokemon(): void
    {
        $this->expectException(PokemonImageUploadException::class);

        $this->pokemonImageStorage->upload(new Pokemon(), $this->createUploadedFile());
    }

    public function testDeleteRemovesExistingObject(): void
    {
        $pokemon = $this->createPokemon(3);
        $objectKey = $this->pokemonImageStorage->upload($pokemon, $this->createUploadedFile());
        $this->pokemonImageStorage->delete($objectKey);

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
        file_put_contents(
            $path,
            base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQP/AABEIAAEAAQMBIgACEQEDEQH/xABTAAEBAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEBAD8Af//Z'),
        );

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
