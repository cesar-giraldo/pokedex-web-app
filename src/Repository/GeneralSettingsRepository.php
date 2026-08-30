<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GeneralSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
}
