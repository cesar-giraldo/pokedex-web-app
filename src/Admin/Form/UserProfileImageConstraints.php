<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Validator\Constraints\Image;

final class UserProfileImageConstraints
{
    public const string MAX_SIZE = '6M';

    /**
     * @return list<Image>
     */
    public static function upload(): array
    {
        return [
            new Image(
                maxSize: self::MAX_SIZE,
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                mimeTypesMessage: 'Solo se permiten imágenes JPG, PNG o WebP.',
                maxSizeMessage: 'La imagen no puede superar {{ limit }} {{ suffix }}.',
            ),
        ];
    }
}
