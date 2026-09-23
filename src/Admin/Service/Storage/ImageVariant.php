<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use InvalidArgumentException;
use LogicException;

use function dirname;
use function pathinfo;
use function sprintf;

use const PATHINFO_FILENAME;

enum ImageVariant: string
{
    case Original = 'original';
    case Avatar = 'avatar';
    case Thumb = 'thumb';
    case Display = 'display';

    public function isOriginal(): bool
    {
        return self::Original === $this;
    }

    /**
     * @return list<self>
     */
    public static function derivedCases(): array
    {
        return [self::Avatar, self::Thumb, self::Display];
    }

    /**
     * @return list<self>
     */
    public static function pokemonGenerated(): array
    {
        return [self::Thumb, self::Display];
    }

    /**
     * @return list<self>
     */
    public static function profileGenerated(): array
    {
        return [self::Avatar, self::Display];
    }

    public function isAllowedForPokemon(): bool
    {
        return match ($this) {
            self::Original, self::Thumb, self::Display => true,
            self::Avatar => false,
        };
    }

    public function isAllowedForProfile(): bool
    {
        return match ($this) {
            self::Original, self::Avatar, self::Display => true,
            self::Thumb => false,
        };
    }

    public function width(): int
    {
        return match ($this) {
            self::Avatar => 128,
            self::Thumb => 400,
            self::Display => 1200,
            self::Original => throw new LogicException('Original has no target size.'),
        };
    }

    public function height(): int
    {
        return $this->width();
    }

    public function isCover(): bool
    {
        return match ($this) {
            self::Avatar, self::Thumb => true,
            self::Display => false,
            self::Original => throw new LogicException('Original has no cover mode.'),
        };
    }

    public function objectKey(string $originalKey): string
    {
        if ($this->isOriginal()) {
            return $originalKey;
        }

        if ('' === $originalKey) {
            throw new InvalidArgumentException('Original object key cannot be empty.');
        }

        $directory = dirname($originalKey);
        $filename = pathinfo($originalKey, PATHINFO_FILENAME);

        if ('.' === $directory) {
            return sprintf('%s_%s.webp', $filename, $this->value);
        }

        return sprintf('%s/%s_%s.webp', $directory, $filename, $this->value);
    }
}
