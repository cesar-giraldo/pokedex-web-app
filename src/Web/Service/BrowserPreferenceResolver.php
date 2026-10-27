<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\GeneralSettings;
use App\Web\Registration\ResolvedBrowserPreferences;
use DateTimeZone;

use function explode;
use function in_array;
use function str_replace;
use function strtolower;
use function strtoupper;
use function trim;

final class BrowserPreferenceResolver
{
    public function resolve(
        ?string $timezone,
        ?string $locale,
        ?string $timeFormat,
        GeneralSettings $settings,
    ): ResolvedBrowserPreferences {
        return new ResolvedBrowserPreferences(
            $this->timezone($timezone, $settings),
            $this->locale($locale, $settings),
            $this->timeFormat($timeFormat, $settings),
        );
    }

    private function timezone(?string $candidate, GeneralSettings $settings): string
    {
        $identifiers = DateTimeZone::listIdentifiers();
        $timezone = trim((string) $candidate);

        if ('' !== $timezone && in_array($timezone, $identifiers, true)) {
            return $timezone;
        }

        $default = $settings->getDefaultTimezone();

        if (in_array($default, $identifiers, true)) {
            return $default;
        }

        return GeneralSettings::DEFAULT_TIMEZONE;
    }

    private function locale(?string $candidate, GeneralSettings $settings): SupportedLocale
    {
        $normalized = str_replace('_', '-', trim((string) $candidate));
        $parts = explode('-', $normalized);

        if (2 === count($parts) && '' !== $parts[0] && '' !== $parts[1]) {
            $normalized = strtolower($parts[0]) . '-' . strtoupper($parts[1]);
        }

        return SupportedLocale::tryFrom($normalized) ?? $settings->getDefaultLocale();
    }

    private function timeFormat(?string $candidate, GeneralSettings $settings): TimeFormat
    {
        return TimeFormat::tryFrom(strtolower(trim((string) $candidate))) ?? $settings->getDefaultTimeFormat();
    }
}
