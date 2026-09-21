<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

final class GdImageManagerFactory
{
    public static function create(): ImageManager
    {
        return new ImageManager(
            Driver::class,
            autoOrientation: true,
            strip: true,
        );
    }
}
