<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\User;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function in_array;
use function is_resource;
use function pathinfo;
use function random_bytes;
use function sprintf;
use function strtolower;

use const PATHINFO_EXTENSION;

final class UserProfileImageStorage
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly FilesystemOperator $privateMediaStorage,
        private readonly string $storagePrefix,
    ) {
    }

    public function upload(User $user, UploadedFile $file): string
    {
        $userId = $user->getId();
        if (null === $userId) {
            throw new RuntimeException('El usuario debe persistirse antes de subir una imagen de perfil.');
        }

        $mimeType = (string) $file->getMimeType();
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;

        if (null === $extension) {
            throw new RuntimeException('Tipo de imagen no permitido.');
        }

        $objectKey = $this->buildObjectKey($userId, $extension);
        $stream = fopen($file->getPathname(), 'r');

        if (false === $stream) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        try {
            $this->privateMediaStorage->writeStream($objectKey, $stream);
        } finally {
            fclose($stream);
        }

        return $objectKey;
    }

    public function delete(?string $objectKey): void
    {
        if (null === $objectKey || '' === $objectKey) {
            return;
        }

        if (!$this->privateMediaStorage->fileExists($objectKey)) {
            return;
        }

        $this->privateMediaStorage->delete($objectKey);
    }

    /**
     * @return resource
     */
    public function readStream(string $objectKey)
    {
        if (!$this->privateMediaStorage->fileExists($objectKey)) {
            throw new RuntimeException('La imagen de perfil no existe.');
        }

        $stream = $this->privateMediaStorage->readStream($objectKey);

        if (!is_resource($stream)) {
            throw new RuntimeException('No se pudo leer la imagen de perfil.');
        }

        return $stream;
    }

    public function resolveMimeType(string $objectKey): string
    {
        $extension = strtolower((string) pathinfo($objectKey, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    public function buildObjectKey(int $userId, string $extension): string
    {
        if (!in_array($extension, self::ALLOWED_MIME_TYPES, true)) {
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
