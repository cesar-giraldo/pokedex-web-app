<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\GeneralSettings;
use App\Web\Service\BrowserPreferenceResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrowserPreferenceResolver::class)]
#[Group('unit')]
final class BrowserPreferenceResolverTest extends TestCase
{
    public function testValidBrowserValuesAreKept(): void
    {
        $resolved = (new BrowserPreferenceResolver())->resolve(
            'America/Mexico_City',
            'es_mx',
            '24h',
            GeneralSettings::createWithDefaults(),
        );

        self::assertSame('America/Mexico_City', $resolved->timezone);
        self::assertSame(SupportedLocale::SpanishMexico, $resolved->locale);
        self::assertSame(TimeFormat::Hour24, $resolved->timeFormat);
    }

    public function testUnknownValuesFallBackToThePlatformDefaults(): void
    {
        $resolved = (new BrowserPreferenceResolver())->resolve(
            'Not/AZone',
            'de-DE',
            'h12',
            GeneralSettings::createWithDefaults(),
        );

        self::assertSame(GeneralSettings::DEFAULT_TIMEZONE, $resolved->timezone);
        self::assertSame(GeneralSettings::DEFAULT_LOCALE, $resolved->locale);
        self::assertSame(GeneralSettings::DEFAULT_TIME_FORMAT, $resolved->timeFormat);
    }
}
