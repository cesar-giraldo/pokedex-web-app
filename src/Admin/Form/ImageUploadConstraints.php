<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Service\Storage\AllowedImageTypes;
use Symfony\Component\Validator\Constraints\Image;

final class ImageUploadConstraints
{
    /**
     * @return list<Image>
     */
    public static function upload(): array
    {
        return [
            new Image(
                maxSize: AllowedImageTypes::MAX_SIZE,
                mimeTypes: AllowedImageTypes::mimeTypes(),
                mimeTypesMessage: 'Solo se permiten imágenes JPG, PNG o WebP.',
                maxSizeMessage: 'La imagen no puede superar {{ limit }} {{ suffix }}.',
            ),
        ];
    }
}
