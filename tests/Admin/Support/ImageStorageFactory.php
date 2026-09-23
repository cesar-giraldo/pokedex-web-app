<?php

declare(strict_types=1);

namespace App\Tests\Admin\Support;

use App\Admin\Service\Storage\GdImageManagerFactory;
use App\Admin\Service\Storage\ImageVariantProcessor;
use App\Admin\Service\Storage\ImageVariantStore;
use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\PokemonImageStorage;
use App\Admin\Service\Storage\UserProfileImageStorage;

final class ImageStorageFactory
{
    public static function variantStore(ObjectStorage $objectStorage): ImageVariantStore
    {
        return new ImageVariantStore(
            $objectStorage,
            new ImageVariantProcessor(GdImageManagerFactory::create()),
        );
    }

    public static function pokemon(ObjectStorage $objectStorage, string $prefix = 'dev'): PokemonImageStorage
    {
        return new PokemonImageStorage($objectStorage, self::variantStore($objectStorage), $prefix);
    }

    public static function profile(ObjectStorage $objectStorage, string $prefix = 'dev'): UserProfileImageStorage
    {
        return new UserProfileImageStorage($objectStorage, self::variantStore($objectStorage), $prefix);
    }
}
