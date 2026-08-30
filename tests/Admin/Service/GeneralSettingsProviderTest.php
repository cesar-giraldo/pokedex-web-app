<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;
use PHPUnit\Framework\TestCase;

final class GeneralSettingsProviderTest extends TestCase
{
    public function testReturnsPersistedSettings(): void
    {
        $settings = GeneralSettings::createWithDefaults();

        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::once())
            ->method('findSingleton')
            ->willReturn($settings);

        $provider = new GeneralSettingsProvider($repository);

        self::assertSame($settings, $provider->get());
        self::assertSame($settings, $provider->get());
    }

    public function testReturnsDefaultSettingsWhenRecordDoesNotExist(): void
    {
        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::once())
            ->method('findSingleton')
            ->willReturn(null);

        $provider = new GeneralSettingsProvider($repository);
        $settings = $provider->get();

        self::assertTrue($settings->isShowHiddenUsers());
        self::assertNull($settings->getId());
    }

    public function testResetCacheForcesReload(): void
    {
        $settings = GeneralSettings::createWithDefaults();

        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::exactly(2))
            ->method('findSingleton')
            ->willReturn($settings);

        $provider = new GeneralSettingsProvider($repository);
        $provider->get();
        $provider->resetCache();
        $provider->get();
    }
}
