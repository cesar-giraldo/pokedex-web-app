<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\PokemonImageStorage;
use App\Admin\Service\Storage\PokemonImageUploadException;
use App\Admin\Service\Storage\PokemonImageUploadService;
use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Repository\PokemonImageRepository;
use App\Tests\Admin\Support\ImageStorageFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use const DIRECTORY_SEPARATOR;

#[CoversClass(PokemonImageUploadService::class)]
#[Group('unit')]
final class PokemonImageUploadServiceTest extends TestCase
{
    private string $tempDirectory = '';

    private PokemonImageStorage $pokemonImageStorage;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-pokemon-upload-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->pokemonImageStorage = ImageStorageFactory::pokemon(
            new ObjectStorage(new Filesystem(new LocalFilesystemAdapter($this->tempDirectory))),
        );
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testUploadAssignsNextSortOrderAndTrimsDescription(): void
    {
        $pokemon = $this->createPokemon(8);
        $repository = $this->createMock(PokemonImageRepository::class);
        $repository->method('getNextSortOrder')->willReturn(3);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service = new PokemonImageUploadService($this->pokemonImageStorage, $repository, $entityManager);
        $image = $service->upload($pokemon, $this->createUploadedFile(), '  Vista lateral  ');

        self::assertSame(3, $image->getSortOrder());
        self::assertSame('Vista lateral', $image->getDescription());
        self::assertSame($pokemon, $image->getPokemon());
        self::assertTrue($pokemon->getImages()->contains($image));
        self::assertMatchesRegularExpression('#^dev/public/pokemon/images/8/[a-f0-9]{32}\.jpg$#', $image->getImagePath());
        self::assertMatchesRegularExpression('#^[1-9A-HJ-NP-Za-km-z]{22}$#', $image->getPublicToken());
    }

    public function testUploadDoesNotWriteWhenFlushFails(): void
    {
        $pokemon = $this->createPokemon(8);
        $repository = $this->createMock(PokemonImageRepository::class);
        $repository->method('getNextSortOrder')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush')->willThrowException(new RuntimeException('db'));

        $service = new PokemonImageUploadService($this->pokemonImageStorage, $repository, $entityManager);

        try {
            $service->upload($pokemon, $this->createUploadedFile(), null);
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            self::assertSame('db', $exception->getMessage());
        }

        self::assertSame([], $this->listFiles($this->tempDirectory));
    }

    public function testUploadRollsBackRowWhenStorageWriteFails(): void
    {
        $pokemon = $this->createPokemon(8);
        $repository = $this->createMock(PokemonImageRepository::class);
        $repository->method('getNextSortOrder')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::exactly(2))->method('flush');
        $entityManager->expects(self::once())->method('remove');

        $service = new PokemonImageUploadService($this->createFailingWriteStorage(), $repository, $entityManager);

        try {
            $service->upload($pokemon, $this->createUploadedFile(), null);
            self::fail('Expected PokemonImageUploadException.');
        } catch (PokemonImageUploadException) {
        }

        self::assertCount(0, $pokemon->getImages());
    }

    public function testReorderUpdatesSortOrder(): void
    {
        $pokemon = $this->createPokemon(4);
        $first = $this->createImage($pokemon, 10, 1);
        $second = $this->createImage($pokemon, 11, 2);

        $repository = $this->createMock(PokemonImageRepository::class);
        $repository->method('findByPokemonOrdered')->willReturn([$first, $second]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new PokemonImageUploadService($this->pokemonImageStorage, $repository, $entityManager);
        $service->reorder($pokemon, [11, 10]);

        self::assertSame(1, $second->getSortOrder());
        self::assertSame(2, $first->getSortOrder());
    }

    public function testReorderRejectsMismatchedIds(): void
    {
        $pokemon = $this->createPokemon(4);
        $image = $this->createImage($pokemon, 10, 1);

        $repository = $this->createMock(PokemonImageRepository::class);
        $repository->method('findByPokemonOrdered')->willReturn([$image]);

        $service = new PokemonImageUploadService(
            $this->pokemonImageStorage,
            $repository,
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->reorder($pokemon, [10, 99]);
    }

    public function testDeleteRemovesFileAndEntity(): void
    {
        $pokemon = $this->createPokemon(2);
        $objectKey = $this->pokemonImageStorage->upload($pokemon, $this->createUploadedFile());
        $image = $this->createImage($pokemon, 21, 1);
        $image->setImagePath($objectKey);

        $repository = $this->createMock(PokemonImageRepository::class);
        $repository->method('findByPokemonOrdered')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($image);
        $entityManager->expects(self::exactly(2))->method('flush');

        $service = new PokemonImageUploadService($this->pokemonImageStorage, $repository, $entityManager);
        $service->delete($pokemon, $image);

        $this->expectException(RuntimeException::class);
        $this->pokemonImageStorage->readStream($objectKey);
    }

    public function testDeleteDoesNotTouchDatabaseWhenStorageDeleteFails(): void
    {
        $pokemon = $this->createPokemon(2);
        $image = $this->createImage($pokemon, 21, 1);
        $image->setImagePath('dev/public/pokemon/images/2/file.jpg');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $service = new PokemonImageUploadService(
            $this->createFailingDeleteStorage(),
            $this->createMock(PokemonImageRepository::class),
            $entityManager,
        );

        try {
            $service->delete($pokemon, $image);
            self::fail('Expected PokemonImageUploadException.');
        } catch (PokemonImageUploadException) {
        }

        self::assertTrue($pokemon->getImages()->contains($image));
    }

    public function testDeleteRejectsImageFromAnotherPokemon(): void
    {
        $pokemon = $this->createPokemon(1);
        $other = $this->createPokemon(2);
        $image = $this->createImage($other, 5, 1);

        $service = new PokemonImageUploadService(
            $this->pokemonImageStorage,
            $this->createMock(PokemonImageRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(NotFoundHttpException::class);
        $service->delete($pokemon, $image);
    }

    private function createPokemon(int $id): Pokemon
    {
        $pokemon = new Pokemon()
            ->setName('Charmander')
            ->setHeight(6)
            ->setWeight(85);

        $reflection = new ReflectionProperty(Pokemon::class, 'id');
        $reflection->setValue($pokemon, $id);

        return $pokemon;
    }

    private function createFailingWriteStorage(): PokemonImageStorage
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('writeStream')->willThrowException(UnableToWriteFile::atLocation('key'));
        $filesystem->method('fileExists')->willReturn(false);

        return ImageStorageFactory::pokemon(new ObjectStorage($filesystem));
    }

    private function createFailingDeleteStorage(): PokemonImageStorage
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('fileExists')->willReturn(true);
        $filesystem->method('delete')->willThrowException(UnableToDeleteFile::atLocation('key'));

        return ImageStorageFactory::pokemon(new ObjectStorage($filesystem));
    }

    /**
     * @return list<string>
     */
    private function listFiles(string $directory): array
    {
        $files = [];
        $items = scandir($directory);
        if (false === $items) {
            return [];
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                foreach ($this->listFiles($path) as $nested) {
                    $files[] = $nested;
                }

                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    private function createImage(Pokemon $pokemon, int $id, int $sortOrder): PokemonImage
    {
        $image = new PokemonImage()
            ->setImagePath('dev/public/pokemon/images/' . $id . '/file.jpg')
            ->setSortOrder($sortOrder);
        $pokemon->addImage($image);

        $reflection = new ReflectionProperty(PokemonImage::class, 'id');
        $reflection->setValue($image, $id);

        return $image;
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pokemon-upload-');
        self::assertNotFalse($path);
        file_put_contents(
            $path,
            base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQP/AABEIAAEAAQMBIgACEQEDEQH/xABTAAEBAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/AABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEBAD8Af//Z'),
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
