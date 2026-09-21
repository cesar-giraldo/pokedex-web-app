<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

use function fclose;
use function fopen;
use function implode;
use function is_resource;
use function str_contains;
use function strtolower;

final class ObjectStorage
{
    public function __construct(
        private readonly FilesystemOperator $privateMediaStorage,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function writeUploadedFile(string $objectKey, UploadedFile $file): void
    {
        $stream = fopen($file->getPathname(), 'r');

        if (false === $stream) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        try {
            $this->privateMediaStorage->writeStream($objectKey, $stream);
        } catch (FilesystemException $exception) {
            throw new ObjectStorageException(
                $this->buildUploadFailureMessage($exception),
                previous: $exception,
            );
        } finally {
            fclose($stream);
        }
    }

    public function write(string $objectKey, string $contents): void
    {
        try {
            $this->privateMediaStorage->write($objectKey, $contents);
        } catch (FilesystemException $exception) {
            throw new ObjectStorageException(
                $this->buildUploadFailureMessage($exception),
                previous: $exception,
            );
        }
    }

    public function read(string $objectKey): string
    {
        if (!$this->privateMediaStorage->fileExists($objectKey)) {
            throw new ObjectStorageException('El archivo no existe.');
        }

        try {
            return $this->privateMediaStorage->read($objectKey);
        } catch (FilesystemException $exception) {
            throw new ObjectStorageException('No se pudo leer el archivo.', previous: $exception);
        }
    }

    public function delete(?string $objectKey): void
    {
        if (null === $objectKey || '' === $objectKey) {
            return;
        }

        try {
            if (!$this->privateMediaStorage->fileExists($objectKey)) {
                return;
            }

            $this->privateMediaStorage->delete($objectKey);
        } catch (FilesystemException $exception) {
            throw new ObjectStorageException(
                $this->buildDeleteFailureMessage($exception),
                previous: $exception,
            );
        }
    }

    public function tryDelete(?string $objectKey): void
    {
        try {
            $this->delete($objectKey);
        } catch (ObjectStorageException $exception) {
            $this->logger->warning('Failed to delete object from storage.', [
                'object_key' => $objectKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return resource
     */
    public function readStream(string $objectKey)
    {
        if (!$this->privateMediaStorage->fileExists($objectKey)) {
            throw new ObjectStorageException('El archivo no existe.');
        }

        $stream = $this->privateMediaStorage->readStream($objectKey);

        if (!is_resource($stream)) {
            throw new ObjectStorageException('No se pudo leer el archivo.');
        }

        return $stream;
    }

    public function fileExists(string $objectKey): bool
    {
        return $this->privateMediaStorage->fileExists($objectKey);
    }

    public function resolveMimeType(string $objectKey): string
    {
        return AllowedImageTypes::mimeTypeFromObjectKey($objectKey);
    }

    private function buildUploadFailureMessage(FilesystemException $exception): string
    {
        return $this->buildStorageFailureMessage(
            $exception,
            'No se pudo guardar el archivo en el almacenamiento. Verifica la configuración de AWS S3.',
        );
    }

    private function buildDeleteFailureMessage(FilesystemException $exception): string
    {
        return $this->buildStorageFailureMessage(
            $exception,
            'No se pudo eliminar el archivo del almacenamiento. Verifica la configuración de AWS S3.',
        );
    }

    private function buildStorageFailureMessage(FilesystemException $exception, string $fallback): string
    {
        $message = strtolower($this->collectExceptionMessages($exception));

        if (str_contains($message, 'accessdenied') || str_contains($message, '403 forbidden')) {
            return 'No se pudo acceder a S3. Revisa los permisos IAM del usuario (s3:PutObject, s3:GetObject, s3:DeleteObject).';
        }

        if (str_contains($message, 'permanent redirect') || str_contains($message, 'correct region')) {
            return 'No se pudo acceder a S3. La región configurada en AWS_REGION no coincide con la del bucket.';
        }

        if (str_contains($message, 'nosuchbucket')) {
            return 'No se pudo acceder a S3. El bucket configurado en AWS_S3_BUCKET no existe o no es accesible.';
        }

        if (str_contains($message, 'invalidaccesskeyid') || str_contains($message, 'signaturedoesnotmatch')) {
            return 'No se pudo acceder a S3. Las credenciales AWS no son válidas.';
        }

        return $fallback;
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
