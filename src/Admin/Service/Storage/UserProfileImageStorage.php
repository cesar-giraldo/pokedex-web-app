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
            $this->objectStorage->delete($objectKey);
        } catch (ObjectStorageException $exception) {
            throw new UserProfileImageUploadException($exception->getMessage(), previous: $exception);
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
            throw new RuntimeException('La imagen de perfil no existe.', previous: $exception);
        }
    }

    public function resolveMimeType(string $objectKey): string
    {
        return $this->objectStorage->resolveMimeType($objectKey);
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
