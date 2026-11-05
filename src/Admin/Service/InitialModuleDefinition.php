<?php

declare(strict_types=1);

namespace App\Admin\Service;

final readonly class InitialModuleDefinition
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $sortOrder,
        public ?string $parentSlug,
        public ?string $description,
        public bool $isEnabled,
    ) {
    }
}
