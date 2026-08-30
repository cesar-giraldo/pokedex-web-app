<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;

final class GeneralSettingsProvider
{
    private ?GeneralSettings $cached = null;

    public function __construct(
        private readonly GeneralSettingsRepository $generalSettingsRepository,
    ) {
    }

    public function get(): GeneralSettings
    {
        if ($this->cached instanceof GeneralSettings) {
            return $this->cached;
        }

        $settings = $this->generalSettingsRepository->findSingleton();

        if (!$settings instanceof GeneralSettings) {
            $settings = GeneralSettings::createWithDefaults();
        }

        $this->cached = $settings;

        return $settings;
    }

    public function resetCache(): void
    {
        $this->cached = null;
    }
}
