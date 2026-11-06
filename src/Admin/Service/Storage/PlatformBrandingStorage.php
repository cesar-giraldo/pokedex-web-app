<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Admin\Service\SvgSanitizer;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function file_get_contents;
use function is_string;
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
        private readonly SvgSanitizer $svgSanitizer = new SvgSanitizer(),
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
        $contents = file_get_contents($file->getPathname());

        if (!is_string($contents)) {
            throw new PlatformBrandingUploadException('No se pudo leer el archivo SVG.');
        }

        $sanitized = $this->svgSanitizer->sanitize($contents);

        if (null === $sanitized) {
            throw new PlatformBrandingUploadException('El archivo SVG no es válido.');
        }

        try {
            $this->objectStorage->write($objectKey, $sanitized);
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
