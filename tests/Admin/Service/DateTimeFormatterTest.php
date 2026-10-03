<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\DateTimeFormatter;
use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Repository\GeneralSettingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[Group('unit')]
final class DateTimeFormatterTest extends TestCase
{
    public function testFormatsUtcInstantInViewerTimezoneWith24HourClock(): void
    {
        $formatter = $this->createFormatter(
            $this->viewer('Europe/Madrid', SupportedLocale::SpanishSpain, TimeFormat::Hour24),
        );

        $formatted = $formatter->format(
            new DateTimeImmutable('2026-09-07 21:46:47', new DateTimeZone('UTC')),
        );

        self::assertStringContainsString('23:46', $formatted);
        self::assertStringContainsString('2026', $formatted);
    }

    public function testFormatsUtcInstantInPlatformTimezoneWith12HourClock(): void
    {
        $formatter = $this->createFormatter(null);

        $formatted = $formatter->format(
            new DateTimeImmutable('2026-09-07 21:46:47', new DateTimeZone('UTC')),
        );

        self::assertMatchesRegularExpression('/4:46/u', $formatted);
        self::assertDoesNotMatchRegularExpression('/\b16:46\b/', $formatted);
    }

    public function testReturnsEmptyPlaceholderForNull(): void
    {
        $formatter = $this->createFormatter(null);

        self::assertSame('—', $formatter->format(null));
        self::assertSame('', $formatter->format(null, ''));
        self::assertSame('—', $formatter->formatRelative(null));
    }

    public function testFormatsRecentInstantAsRelativeSpanish(): void
    {
        $formatter = $this->createFormatter(
            $this->viewer('America/Bogota', SupportedLocale::SpanishColombia, TimeFormat::Hour24),
        );
        $now = new DateTimeImmutable('2026-10-02 12:00:00', new DateTimeZone('UTC'));
        $value = new DateTimeImmutable('2026-10-02 11:55:00', new DateTimeZone('UTC'));

        self::assertSame('hace 5 min', $formatter->formatRelative($value, '—', null, $now));
    }

    public function testFormatsRecentInstantAsRelativeEnglish(): void
    {
        $formatter = $this->createFormatter(
            $this->viewer('America/New_York', SupportedLocale::EnglishUnitedStates, TimeFormat::Hour12),
        );
        $now = new DateTimeImmutable('2026-10-02 12:00:00', new DateTimeZone('UTC'));
        $value = new DateTimeImmutable('2026-10-02 11:00:00', new DateTimeZone('UTC'));

        self::assertSame('1 hr ago', $formatter->formatRelative($value, '—', null, $now));
    }

    public function testFallsBackToAbsoluteFormatAfterSevenDays(): void
    {
        $formatter = $this->createFormatter(
            $this->viewer('Europe/Madrid', SupportedLocale::SpanishSpain, TimeFormat::Hour24),
        );
        $now = new DateTimeImmutable('2026-10-02 12:00:00', new DateTimeZone('UTC'));
        $value = new DateTimeImmutable('2026-09-20 21:46:47', new DateTimeZone('UTC'));

        $formatted = $formatter->formatRelative($value, '—', null, $now);

        self::assertStringContainsString('2026', $formatted);
        self::assertStringNotContainsString('hace', $formatted);
    }

    private function createFormatter(?User $viewer): DateTimeFormatter
    {
        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->method('findSingleton')->willReturn(GeneralSettings::createWithDefaults());

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($viewer);

        return new DateTimeFormatter(new GeneralSettingsProvider($repository), $security);
    }

    private function viewer(string $timezone, SupportedLocale $locale, TimeFormat $timeFormat): User
    {
        return new User()
            ->setTimezone($timezone)
            ->setLocale($locale)
            ->setTimeFormat($timeFormat);
    }
}
