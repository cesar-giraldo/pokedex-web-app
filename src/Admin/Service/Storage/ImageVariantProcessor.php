<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

final class ImageVariantProcessor
{
    public const int WEBP_QUALITY = 80;

    public function __construct(
        private readonly ImageManager $imageManager,
    ) {
    }

    public function process(string $binary, ImageVariant $variant): string
    {
        if ($variant->isOriginal()) {
            throw new InvalidArgumentException('Cannot process the original image as a derived variant.');
        }

        $image = $this->imageManager->decodeBinary($binary);

        if ($variant->isCover()) {
            $image->cover($variant->width(), $variant->height());
        } else {
            $image->scaleDown($variant->width(), $variant->height());
        }

        return $image->encode(new WebpEncoder(quality: self::WEBP_QUALITY, strip: true))->toString();
    }
}
