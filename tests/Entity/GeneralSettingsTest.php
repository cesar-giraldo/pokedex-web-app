<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use DateTime;
use PHPUnit\Framework\TestCase;

final class GeneralSettingsTest extends TestCase
{
    public function testCreatesWithDefaultValues(): void
    {
        $settings = GeneralSettings::createWithDefaults();

        self::assertTrue($settings->isShowHiddenUsers());
        self::assertSame([SupportedLanguage::Spanish->value], $settings->getEnabledLanguages());
        self::assertSame(SupportedLanguage::Spanish->value, $settings->getWebsiteDefaultLanguage());
        self::assertInstanceOf(DateTime::class, $settings->getLastUpdatedAt());
    }

    public function testUpdatesLastUpdatedAtOnTouch(): void
    {
        $settings = GeneralSettings::createWithDefaults();
        $settings->setLastUpdatedAt(new DateTime('2020-01-01 00:00:00'));

        $settings->touchLastUpdatedAt();

        self::assertGreaterThan(
            new DateTime('2020-01-01 00:00:00')->getTimestamp(),
            $settings->getLastUpdatedAt()->getTimestamp(),
        );
    }

    public function testWebsiteDefaultLanguageMustBeEnabled(): void
    {
        $settings = GeneralSettings::createWithDefaults()
            ->setEnabledLanguages([SupportedLanguage::English->value])
            ->setWebsiteDefaultLanguage(SupportedLanguage::Spanish->value);

        self::assertFalse($settings->isWebsiteDefaultLanguageEnabled());
    }

    public function testReturnsEnabledLanguageLabels(): void
    {
        $settings = GeneralSettings::createWithDefaults()
            ->setEnabledLanguages([
                SupportedLanguage::Spanish->value,
                SupportedLanguage::English->value,
            ]);

        self::assertSame(
            [
                SupportedLanguage::Spanish->label(),
                SupportedLanguage::English->label(),
            ],
            $settings->getEnabledLanguageLabels(),
        );
    }
}
