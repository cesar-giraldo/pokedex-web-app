<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

final readonly class StagedPlatformBrandingFile
{
    public function __construct(
        public PlatformBrandingAsset $asset,
        public ?string $previousPath,
        public string $newPath,
    ) {
    }
}
