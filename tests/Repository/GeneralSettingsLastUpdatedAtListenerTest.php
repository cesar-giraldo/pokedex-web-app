<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function bin2hex;
use function random_bytes;

#[Group('integration')]
final class GeneralSettingsLastUpdatedAtListenerTest extends KernelTestCase
{
    private ?string $previousPlatformName = null;

    private bool $restorePlatformName = false;

    protected function tearDown(): void
    {
        if ($this->restorePlatformName) {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $settings = $this->repository()->findSingleton();

            if ($settings instanceof GeneralSettings) {
                $settings->setPlatformName($this->previousPlatformName);
                $entityManager->flush();
            }
        }

        parent::tearDown();
    }

    public function testPersistsLastUpdatedAtWhenSettingsChange(): void
    {
        self::bootKernel();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $settings = $this->repository()->getOrCreateSingleton();
        if (null === $settings->getId()) {
            $entityManager->flush();
        }

        $settingsId = $settings->getId();
        self::assertNotNull($settingsId);

        $this->previousPlatformName = $settings->getPlatformName();
        $this->restorePlatformName = true;

        $frozen = new DateTime('2020-01-15 08:30:00');
        $entityManager->getConnection()->update(
            'general_settings',
            ['last_updated_at' => $frozen],
            ['id' => $settingsId],
            ['last_updated_at' => Types::DATETIME_MUTABLE],
        );
        $entityManager->refresh($settings);

        self::assertSame(
            $frozen->format('Y-m-d H:i:s'),
            $settings->getLastUpdatedAt()->format('Y-m-d H:i:s'),
        );

        $settings->setPlatformName('Marca ' . bin2hex(random_bytes(4)));
        $entityManager->flush();
        $entityManager->refresh($settings);

        self::assertGreaterThan(
            $frozen->getTimestamp(),
            $settings->getLastUpdatedAt()->getTimestamp(),
        );
    }

    private function repository(): GeneralSettingsRepository
    {
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        self::assertInstanceOf(GeneralSettingsRepository::class, $repository);

        return $repository;
    }
}
