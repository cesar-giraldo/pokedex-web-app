<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use DateTime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GeneralSettingsTest extends TestCase
{
    public function testCreatesWithDefaultValues(): void
    {
        $settings = GeneralSettings::createWithDefaults();

        self::assertTrue($settings->isShowHiddenUsers());
        self::assertSame([SupportedLanguage::Spanish->value], $settings->getEnabledLanguages());
        self::assertSame(SupportedLanguage::Spanish->value, $settings->getWebsiteDefaultLanguage());
        self::assertSame(GeneralSettings::DEFAULT_TIMEZONE, $settings->getDefaultTimezone());
        self::assertSame(GeneralSettings::DEFAULT_LOCALE, $settings->getDefaultLocale());
        self::assertSame(GeneralSettings::DEFAULT_TIME_FORMAT, $settings->getDefaultTimeFormat());
        self::assertInstanceOf(DateTime::class, $settings->getLastUpdatedAt());
        self::assertNull($settings->getLastBackupFilePath());
        self::assertNull($settings->getLastBackupGeneratedAt());
        self::assertFalse($settings->hasLastDatabaseBackup());
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

    public function testTracksLastDatabaseBackup(): void
    {
        $generatedAt = new DateTime('2026-09-23 03:30:45');
        $settings = GeneralSettings::createWithDefaults()
            ->setLastBackupFilePath('dev/private/database-backups/backup-20260923T033045Z.sql')
            ->setLastBackupGeneratedAt($generatedAt);

        self::assertTrue($settings->hasLastDatabaseBackup());
        self::assertSame(
            'dev/private/database-backups/backup-20260923T033045Z.sql',
            $settings->getLastBackupFilePath(),
        );
        self::assertSame($generatedAt, $settings->getLastBackupGeneratedAt());

        $settings->setLastBackupFilePath('  ');

        self::assertNull($settings->getLastBackupFilePath());
        self::assertFalse($settings->hasLastDatabaseBackup());
    }
}
