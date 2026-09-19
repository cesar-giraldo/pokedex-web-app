<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\Pokemon;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function random_bytes;
use function sprintf;

final class PokemonImageStorage
{
    public function __construct(
        private readonly ObjectStorage $objectStorage,
        private readonly string $storagePrefix,
    ) {
    }

    public function allocateObjectKey(Pokemon $pokemon, UploadedFile $file): string
    {
        $pokemonId = $pokemon->getId();
        if (null === $pokemonId) {
            throw new PokemonImageUploadException(
                'El Pokémon debe persistirse antes de subir una imagen.'
            );
        }

        $extension = AllowedImageTypes::resolveExtension($file);
        if (null === $extension) {
            throw new PokemonImageUploadException('Solo se permiten imágenes JPG, PNG o WebP.');
        }

        return $this->buildObjectKey($pokemonId, $extension);
    }

    public function write(string $objectKey, UploadedFile $file): void
    {
        try {
            $this->objectStorage->writeUploadedFile($objectKey, $file);
        } catch (ObjectStorageException $exception) {
            throw new PokemonImageUploadException($exception->getMessage(), previous: $exception);
        }
    }

    public function upload(Pokemon $pokemon, UploadedFile $file): string
    {
        $objectKey = $this->allocateObjectKey($pokemon, $file);
        $this->write($objectKey, $file);

        return $objectKey;
    }

    public function delete(?string $objectKey): void
    {
        try {
            $this->objectStorage->delete($objectKey);
        } catch (ObjectStorageException $exception) {
            throw new PokemonImageUploadException($exception->getMessage(), previous: $exception);
        }
    }

    public function tryDelete(?string $objectKey): void
    {
        $this->objectStorage->tryDelete($objectKey);
    }

    /**
     * @return resource
     */
    public function readStream(string $objectKey)
    {
        try {
            return $this->objectStorage->readStream($objectKey);
        } catch (ObjectStorageException $exception) {
            throw new RuntimeException('La imagen del Pokémon no existe.', previous: $exception);
        }
    }

    public function resolveMimeType(string $objectKey): string
    {
        return $this->objectStorage->resolveMimeType($objectKey);
    }

    public function buildObjectKey(int $pokemonId, string $extension): string
    {
        if (!AllowedImageTypes::isAllowedExtension($extension)) {
            throw new RuntimeException('Extensión de imagen no permitida.');
        }

        return sprintf(
            '%s/public/pokemon/images/%d/%s.%s',
            $this->storagePrefix,
            $pokemonId,
            bin2hex(random_bytes(16)),
            $extension,
        );
    }
}
