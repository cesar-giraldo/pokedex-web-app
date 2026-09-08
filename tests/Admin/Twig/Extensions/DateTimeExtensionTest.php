<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Extensions;

use App\Admin\Service\DateTimeFormatter;
use App\Admin\Service\GeneralSettingsProvider;
use App\Admin\Twig\Extensions\DateTimeExtension;
use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[CoversClass(DateTimeExtension::class)]
#[Group('unit')]
final class DateTimeExtensionTest extends TestCase
{
    public function testRegistersTwigFilters(): void
    {
        $extension = new DateTimeExtension($this->createFormatter());
        $filters = $extension->getFilters();

        self::assertCount(2, $filters);
        self::assertSame('app_datetime', $filters[0]->getName());
        self::assertSame('timezone_label', $filters[1]->getName());
    }

    public function testFormatsDateTimeThroughService(): void
    {
        $value = new DateTimeImmutable('2026-09-07 21:46:47', new DateTimeZone('UTC'));
        $extension = new DateTimeExtension($this->createFormatter());

        $formatted = $extension->formatDateTime($value);

        self::assertMatchesRegularExpression('/4:46/u', $formatted);
        self::assertStringContainsString('2026', $formatted);
    }

    public function testTimezoneLabelIncludesIdentifier(): void
    {
        $extension = new DateTimeExtension($this->createFormatter());

        self::assertStringContainsString('America/Bogota', $extension->timezoneLabel('America/Bogota'));
    }

    private function createFormatter(): DateTimeFormatter
    {
        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->method('findSingleton')->willReturn(GeneralSettings::createWithDefaults());

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        return new DateTimeFormatter(new GeneralSettingsProvider($repository), $security);
    }
}
