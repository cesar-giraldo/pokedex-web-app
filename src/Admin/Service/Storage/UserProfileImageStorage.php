<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\User;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function random_bytes;
use function sprintf;

final class UserProfileImageStorage
{
    public function __construct(
        private readonly ObjectStorage $objectStorage,
        private readonly ImageVariantStore $variantStore,
        private readonly string $storagePrefix,
    ) {
    }

    public function allocateObjectKey(User $user, UploadedFile $file): string
    {
        $userId = $user->getId();
        if (null === $userId) {
            throw new UserProfileImageUploadException(
                'El usuario debe persistirse antes de subir una imagen de perfil.'
            );
        }

        $extension = AllowedImageTypes::resolveExtension($file);
        if (null === $extension) {
            throw new UserProfileImageUploadException('Solo se permiten imágenes JPG, PNG o WebP.');
        }

        return $this->buildObjectKey($userId, $extension);
    }

    public function write(string $objectKey, UploadedFile $file): void
    {
        try {
            $this->objectStorage->writeUploadedFile($objectKey, $file);
        } catch (ObjectStorageException $exception) {
            throw new UserProfileImageUploadException($exception->getMessage(), previous: $exception);
        }

        try {
            $this->variantStore->generate($objectKey, $file->getContent(), ImageVariant::profileGenerated());
        } catch (ImageVariantGenerationException $exception) {
            $this->variantStore->tryDeleteAll($objectKey);

            throw new UserProfileImageUploadException(
                'No se pudieron generar las versiones de la imagen. Inténtalo de nuevo.',
                previous: $exception,
            );
        }
    }

    public function upload(User $user, UploadedFile $file): string
    {
        $objectKey = $this->allocateObjectKey($user, $file);
        $this->write($objectKey, $file);

        return $objectKey;
    }

    public function delete(?string $objectKey): void
    {
        try {
            $this->variantStore->deleteAll($objectKey);
        } catch (ObjectStorageException $exception) {
            throw new UserProfileImageUploadException($exception->getMessage(), previous: $exception);
        }
    }

    public function tryDelete(?string $objectKey): void
    {
        $this->variantStore->tryDeleteAll($objectKey);
    }

    /**
     * @return resource
     */
    public function readStream(string $objectKey, ImageVariant $variant = ImageVariant::Original)
    {
        try {
            return $this->variantStore->readStream($objectKey, $variant);
        } catch (ObjectStorageException|ImageVariantGenerationException $exception) {
            throw new RuntimeException('La imagen de perfil no existe.', previous: $exception);
        }
    }

    public function resolveMimeType(string $objectKey, ImageVariant $variant = ImageVariant::Original): string
    {
        return $this->variantStore->resolveMimeType($objectKey, $variant);
    }

    public function buildObjectKey(int $userId, string $extension): string
    {
        if (!AllowedImageTypes::isAllowedExtension($extension)) {
            throw new RuntimeException('Extensión de imagen no permitida.');
        }

        return sprintf(
            '%s/private/user/profile-images/%d/%s.%s',
            $this->storagePrefix,
            $userId,
            bin2hex(random_bytes(16)),
            $extension,
        );
    }
}
