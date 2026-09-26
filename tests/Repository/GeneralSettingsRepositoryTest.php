<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
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

    public function testRecordLastDatabaseBackupDoesNotTouchLastUpdatedAt(): void
    {
        self::bootKernel();

        /** @var GeneralSettingsRepository $repository */
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $settings = $repository->getOrCreateSingleton();
        if (null === $settings->getId()) {
            $entityManager->flush();
        }

        $previousPath = $settings->getLastBackupFilePath();
        $previousGeneratedAt = $settings->getLastBackupGeneratedAt();
        $previousUpdatedAt = clone $settings->getLastUpdatedAt();
        $generatedAt = new DateTimeImmutable('2026-09-23 03:30:45', new DateTimeZone('UTC'));

        try {
            $repository->recordLastDatabaseBackup(
                'test/private/database-backups/backup-20260923T033045Z.sql',
                $generatedAt,
            );

            $entityManager->refresh($settings);

            self::assertSame(
                'test/private/database-backups/backup-20260923T033045Z.sql',
                $settings->getLastBackupFilePath(),
            );
            self::assertNotNull($settings->getLastBackupGeneratedAt());
            self::assertSame(
                $generatedAt->format('Y-m-d H:i:s'),
                $settings->getLastBackupGeneratedAt()->format('Y-m-d H:i:s'),
            );
            self::assertSame(
                $previousUpdatedAt->format('Y-m-d H:i:s'),
                $settings->getLastUpdatedAt()->format('Y-m-d H:i:s'),
            );
        } finally {
            if (null === $previousPath && null === $previousGeneratedAt) {
                $entityManager->getConnection()->update(
                    'general_settings',
                    [
                        'last_backup_file_path' => null,
                        'last_backup_generated_at' => null,
                    ],
                    ['id' => $settings->getId()],
                );
            } else {
                $repository->recordLastDatabaseBackup(
                    (string) $previousPath,
                    $previousGeneratedAt ?? $generatedAt,
                );
            }

            $entityManager->clear();
        }
    }
}
