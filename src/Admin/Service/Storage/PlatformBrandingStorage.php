<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function preg_match;
use function random_bytes;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

final class PlatformBrandingStorage
{
    public const string MIME_TYPE = 'image/svg+xml';

    public function __construct(
        private readonly ObjectStorage $objectStorage,
        private readonly string $storagePrefix,
    ) {
    }

    public function allocateObjectKey(PlatformBrandingAsset $asset): string
    {
        return sprintf(
            '%s/private/settings/branding/%s/%s.svg',
            $this->storagePrefix,
            $asset->value,
            bin2hex(random_bytes(16)),
        );
    }

    public function write(string $objectKey, UploadedFile $file): void
    {
        try {
            $this->objectStorage->writeUploadedFile($objectKey, $file);
        } catch (ObjectStorageException $exception) {
            throw new PlatformBrandingUploadException($exception->getMessage(), previous: $exception);
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
            throw new RuntimeException('El archivo de marca no existe.', previous: $exception);
        }
    }

    public function isAllowedKey(PlatformBrandingAsset $asset, string $objectKey): bool
    {
        $prefix = sprintf('%s/private/settings/branding/%s/', $this->storagePrefix, $asset->value);

        if (!str_starts_with($objectKey, $prefix)) {
            return false;
        }

        $filename = substr($objectKey, strlen($prefix));

        return 1 === preg_match('/^[a-f0-9]{32}\.svg$/', $filename);
    }
}
