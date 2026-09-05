<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\User;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

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
            throw new UserProfileImageUploadException(
                'El usuario debe persistirse antes de subir una imagen de perfil.'
            );
        }

        $mimeType = (string) $file->getMimeType();
        $extension = $this->resolveExtension($file, $mimeType);

        if (null === $extension) {
            throw new UserProfileImageUploadException('Solo se permiten imágenes JPG, PNG o WebP.');
        }

        $objectKey = $this->buildObjectKey($userId, $extension);
        $stream = fopen($file->getPathname(), 'r');

        if (false === $stream) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        try {
            $this->privateMediaStorage->writeStream($objectKey, $stream);
        } catch (FilesystemException $exception) {
            throw new UserProfileImageUploadException(
                $this->buildUploadFailureMessage($exception),
                previous: $exception
            );
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

    private function resolveExtension(UploadedFile $file, string $mimeType): ?string
    {
        if (isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            return self::ALLOWED_MIME_TYPES[$mimeType];
        }

        $clientExtension = strtolower((string) $file->getClientOriginalExtension());

        return in_array($clientExtension, self::ALLOWED_MIME_TYPES, true)
            ? $clientExtension
            : null;
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

    private function buildUploadFailureMessage(FilesystemException $exception): string
    {
        $message = strtolower($this->collectExceptionMessages($exception));

        if (str_contains($message, 'accessdenied') || str_contains($message, '403 forbidden')) {
            return 'No se pudo guardar la imagen en S3. Revisa los permisos IAM del usuario (s3:PutObject, s3:GetObject, s3:DeleteObject).';
        }

        if (str_contains($message, 'permanent redirect') || str_contains($message, 'correct region')) {
            return 'No se pudo guardar la imagen en S3. La región configurada en AWS_REGION no coincide con la del bucket.';
        }

        if (str_contains($message, 'nosuchbucket')) {
            return 'No se pudo guardar la imagen en S3. El bucket configurado en AWS_S3_BUCKET no existe o no es accesible.';
        }

        if (str_contains($message, 'invalidaccesskeyid') || str_contains($message, 'signaturedoesnotmatch')) {
            return 'No se pudo guardar la imagen en S3. Las credenciales AWS no son válidas.';
        }

        return 'No se pudo guardar la imagen de perfil en el almacenamiento. Verifica la configuración de AWS S3.';
    }

    private function collectExceptionMessages(Throwable $throwable): string
    {
        $messages = [];
        $current = $throwable;

        while (null !== $current) {
            $messages[] = $current->getMessage();
            $current = $current->getPrevious();
        }

        return implode(' ', $messages);
    }
}
