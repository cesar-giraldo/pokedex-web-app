<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Entity\Enum\TimeFormat;
use App\Entity\User;
use DateTimeInterface;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Symfony\Bundle\SecurityBundle\Security;

use function is_string;

/**
 * Formats instants stored in UTC using the viewer's timezone, locale and time format.
 */
final class DateTimeFormatter
{
    public function __construct(
        private readonly GeneralSettingsProvider $generalSettingsProvider,
        private readonly Security $security,
    ) {
    }

    public function format(?DateTimeInterface $value, string $empty = '—', ?User $viewer = null): string
    {
        if (!$value instanceof DateTimeInterface) {
            return $empty;
        }

        $context = $this->resolveContext($viewer);
        $pattern = $this->patternFor($context['locale'], $context['timeFormat']);

        $formatter = new IntlDateFormatter(
            $context['locale'],
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $context['timezone'],
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );

        $formatted = $formatter->format($value);

        return is_string($formatted) && '' !== $formatted ? $formatted : $empty;
    }

    public function timezoneLabel(?User $viewer = null): string
    {
        return $this->resolveContext($viewer)['timezone'];
    }

    /**
     * @return array{timezone: string, locale: string, timeFormat: TimeFormat}
     */
    public function resolveContext(?User $viewer = null): array
    {
        $viewer ??= $this->currentUser();
        $settings = $this->generalSettingsProvider->get();

        $timezone = $viewer instanceof User && '' !== $viewer->getTimezone()
            ? $viewer->getTimezone()
            : $settings->getDefaultTimezone();

        $locale = $viewer instanceof User
            ? $viewer->getLocale()
            : $settings->getDefaultLocale();

        $timeFormat = $viewer instanceof User
            ? $viewer->getTimeFormat()
            : $settings->getDefaultTimeFormat();

        return [
            'timezone' => $timezone,
            'locale' => $locale->intlLocale(),
            'timeFormat' => $timeFormat,
        ];
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    private function patternFor(string $locale, TimeFormat $timeFormat): string
    {
        $skeleton = $timeFormat->isHour12() ? 'yMd hm' : 'yMd Hm';
        $generator = new IntlDatePatternGenerator($locale);
        $pattern = $generator->getBestPattern($skeleton);

        if (is_string($pattern) && '' !== $pattern) {
            return $pattern;
        }

        return $timeFormat->isHour12() ? 'dd/MM/yyyy hh:mm a' : 'dd/MM/yyyy HH:mm';
    }
}
