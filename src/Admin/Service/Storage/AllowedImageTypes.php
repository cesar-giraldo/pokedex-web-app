<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

use function in_array;
use function pathinfo;
use function strtolower;

use const PATHINFO_EXTENSION;

final class AllowedImageTypes
{
    public const string MAX_SIZE = '6M';

    public const int MAX_SIZE_KB = 6144;

    public const string ACCEPT_ATTRIBUTE = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp';

    /**
     * @var array<string, string>
     */
    public const array MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return list<non-empty-string>
     */
    public static function mimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
    }

    public static function resolveExtension(UploadedFile $file): ?string
    {
        return self::MIME_TO_EXTENSION[(string) $file->getMimeType()] ?? null;
    }

    public static function isAllowedExtension(string $extension): bool
    {
        return in_array($extension, self::MIME_TO_EXTENSION, true);
    }

    public static function mimeTypeFromObjectKey(string $objectKey): string
    {
        $extension = strtolower((string) pathinfo($objectKey, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
