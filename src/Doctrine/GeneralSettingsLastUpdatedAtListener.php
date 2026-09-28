<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\GeneralSettings;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: GeneralSettings::class)]
final class GeneralSettingsLastUpdatedAtListener
{
    public function preUpdate(GeneralSettings $settings, PreUpdateEventArgs $event): void
    {
        $previous = $settings->getLastUpdatedAt();
        $now = new DateTime();
        $settings->setLastUpdatedAt($now);

        $event->getObjectManager()->getUnitOfWork()->scheduleExtraUpdate($settings, [
            'lastUpdatedAt' => [$previous, $now],
        ]);
    }
}
