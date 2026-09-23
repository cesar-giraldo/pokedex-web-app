<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ImageVariant;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageVariant::class)]
#[Group('unit')]
final class ImageVariantTest extends TestCase
{
    public function testDerivesWebpObjectKeysFromOriginal(): void
    {
        $original = 'dev/public/pokemon/images/12/abcdefabcdefabcdefabcdefabcdefab.jpg';

        self::assertSame($original, ImageVariant::Original->objectKey($original));
        self::assertSame(
            'dev/public/pokemon/images/12/abcdefabcdefabcdefabcdefabcdefab_thumb.webp',
            ImageVariant::Thumb->objectKey($original),
        );
        self::assertSame(
            'dev/public/pokemon/images/12/abcdefabcdefabcdefabcdefabcdefab_display.webp',
            ImageVariant::Display->objectKey($original),
        );
        self::assertSame(
            'dev/private/user/profile-images/1/file_avatar.webp',
            ImageVariant::Avatar->objectKey('dev/private/user/profile-images/1/file.png'),
        );
    }

    public function testDerivesKeyWithoutDirectory(): void
    {
        self::assertSame('file_thumb.webp', ImageVariant::Thumb->objectKey('file.jpg'));
    }

    public function testRejectsEmptyOriginalKeyForDerivedVariant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImageVariant::Thumb->objectKey('');
    }

    public function testOriginalHasNoTargetSize(): void
    {
        $this->expectException(LogicException::class);
        ImageVariant::Original->width();
    }

    public function testAllowedContextsAndGeneratedPresets(): void
    {
        self::assertTrue(ImageVariant::Thumb->isAllowedForPokemon());
        self::assertFalse(ImageVariant::Avatar->isAllowedForPokemon());
        self::assertTrue(ImageVariant::Avatar->isAllowedForProfile());
        self::assertFalse(ImageVariant::Thumb->isAllowedForProfile());
        self::assertSame([ImageVariant::Thumb, ImageVariant::Display], ImageVariant::pokemonGenerated());
        self::assertSame([ImageVariant::Avatar, ImageVariant::Display], ImageVariant::profileGenerated());
        self::assertTrue(ImageVariant::Thumb->isCover());
        self::assertFalse(ImageVariant::Display->isCover());
        self::assertSame(128, ImageVariant::Avatar->width());
        self::assertSame(400, ImageVariant::Thumb->height());
        self::assertSame(1200, ImageVariant::Display->width());
    }
}
