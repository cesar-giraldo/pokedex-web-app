<?php

declare(strict_types=1);

namespace App\Module\Twig;

use App\Module\ModuleAvailability;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ModuleExtension extends AbstractExtension
{
    public function __construct(
        private readonly ModuleAvailability $moduleAvailability,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_module_enabled', $this->moduleAvailability->isEnabled(...)),
        ];
    }
}
