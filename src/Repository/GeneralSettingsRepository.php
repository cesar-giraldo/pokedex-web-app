<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GeneralSettings;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

/**
 * @extends ServiceEntityRepository<GeneralSettings>
 */
class GeneralSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeneralSettings::class);
    }

    public function findSingleton(): ?GeneralSettings
    {
        /** @var GeneralSettings|null $settings */
        $settings = $this->createQueryBuilder('settings')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $settings;
    }

    public function getOrCreateSingleton(): GeneralSettings
    {
        $settings = $this->findSingleton();

        if ($settings instanceof GeneralSettings) {
            return $settings;
        }

        $settings = GeneralSettings::createWithDefaults();
        $this->getEntityManager()->persist($settings);

        return $settings;
    }

    public function recordLastDatabaseBackup(string $objectKey, DateTimeInterface $generatedAt): void
    {
        $entityManager = $this->getEntityManager();
        $settings = $this->getOrCreateSingleton();

        if (null === $settings->getId()) {
            $entityManager->flush();
        }

        $settingsId = $settings->getId();
        if (null === $settingsId) {
            throw new RuntimeException('No se pudo persistir la configuración general.');
        }

        $entityManager->getConnection()->update(
            'general_settings',
            [
                'last_backup_file_path' => $objectKey,
                'last_backup_generated_at' => DateTime::createFromInterface($generatedAt),
            ],
            ['id' => $settingsId],
            [
                'last_backup_file_path' => Types::STRING,
                'last_backup_generated_at' => Types::DATETIME_MUTABLE,
            ],
        );

        $entityManager->refresh($settings);
    }
}
