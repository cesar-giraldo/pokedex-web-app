<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GeneralSettingsRepositoryTest extends KernelTestCase
{
    public function testGetOrCreateSingletonReturnsSameRecord(): void
    {
        self::bootKernel();

        /** @var GeneralSettingsRepository $repository */
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);

        $existing = $repository->findSingleton();
        if ($existing instanceof GeneralSettings) {
            self::assertSame($existing, $repository->getOrCreateSingleton());

            return;
        }

        $settings = $repository->getOrCreateSingleton();
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->flush();

        self::assertInstanceOf(GeneralSettings::class, $settings);
        self::assertNotNull($settings->getId());
        self::assertTrue($settings->isShowHiddenUsers());
        self::assertSame([SupportedLanguage::Spanish->value], $settings->getEnabledLanguages());
        self::assertSame($settings, $repository->getOrCreateSingleton());
    }
}
