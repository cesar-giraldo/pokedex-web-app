<?php

declare(strict_types=1);

namespace App\Web\Registration;

use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;

final readonly class ResolvedBrowserPreferences
{
    public function __construct(
        public string $timezone,
        public SupportedLocale $locale,
        public TimeFormat $timeFormat,
    ) {
    }
}
