<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Entity\Enum\TimeFormat;
use App\Entity\User;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Symfony\Bundle\SecurityBundle\Security;

use function is_string;
use function sprintf;
use function strstr;

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

    /**
     * Relative label for recent instants. Older than 7 days falls back to the absolute viewer format.
     */
    public function formatRelative(
        ?DateTimeInterface $value,
        string $empty = '—',
        ?User $viewer = null,
        ?DateTimeInterface $now = null,
    ): string {
        if (!$value instanceof DateTimeInterface) {
            return $empty;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $seconds = $now->getTimestamp() - $value->getTimestamp();

        if ($seconds < 0) {
            return $this->format($value, $empty, $viewer);
        }

        $locale = $this->resolveContext($viewer)['locale'];
        $language = strstr($locale, '_', true);
        $language = is_string($language) && '' !== $language ? $language : $locale;

        if ($seconds < 60) {
            return $this->relativeJustNow($language);
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $this->relativeMinutes($language, $minutes);
        }

        $hours = intdiv($seconds, 3600);
        if ($hours < 24) {
            return $this->relativeHours($language, $hours);
        }

        $days = intdiv($seconds, 86400);
        if ($days <= 7) {
            return $this->relativeDays($language, $days);
        }

        return $this->format($value, $empty, $viewer);
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

    private function relativeJustNow(string $language): string
    {
        return match ($language) {
            'en' => 'Just now',
            'pt' => 'Agora',
            'fr' => "À l'instant",
            default => 'Ahora',
        };
    }

    private function relativeMinutes(string $language, int $minutes): string
    {
        return match ($language) {
            'en' => 1 === $minutes ? '1 min ago' : sprintf('%d min ago', $minutes),
            'pt' => 1 === $minutes ? 'há 1 min' : sprintf('há %d min', $minutes),
            'fr' => 1 === $minutes ? 'il y a 1 min' : sprintf('il y a %d min', $minutes),
            default => 1 === $minutes ? 'hace 1 min' : sprintf('hace %d min', $minutes),
        };
    }

    private function relativeHours(string $language, int $hours): string
    {
        return match ($language) {
            'en' => 1 === $hours ? '1 hr ago' : sprintf('%d hr ago', $hours),
            'pt' => 1 === $hours ? 'há 1 h' : sprintf('há %d h', $hours),
            'fr' => 1 === $hours ? 'il y a 1 h' : sprintf('il y a %d h', $hours),
            default => 1 === $hours ? 'hace 1 h' : sprintf('hace %d h', $hours),
        };
    }

    private function relativeDays(string $language, int $days): string
    {
        return match ($language) {
            'en' => 1 === $days ? '1 day ago' : sprintf('%d days ago', $days),
            'pt' => 1 === $days ? 'há 1 dia' : sprintf('há %d dias', $days),
            'fr' => 1 === $days ? 'il y a 1 jour' : sprintf('il y a %d jours', $days),
            default => 1 === $days ? 'hace 1 día' : sprintf('hace %d días', $days),
        };
    }
}
