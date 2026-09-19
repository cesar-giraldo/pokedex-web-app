<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Service\Storage\AllowedImageTypes;
use Symfony\Component\Validator\Constraints\Image;

final class UserProfileImageConstraints
{
    public const string MAX_SIZE = AllowedImageTypes::MAX_SIZE;

    /**
     * @return list<Image>
     */
    public static function upload(): array
    {
        return ImageUploadConstraints::upload();
    }
}
