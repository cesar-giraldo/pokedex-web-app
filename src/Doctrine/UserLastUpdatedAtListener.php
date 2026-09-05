<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\User;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

use function array_keys;

#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: User::class)]
final class UserLastUpdatedAtListener
{
    public function __construct(
        private readonly UserLastUpdatedAtPolicy $policy,
    ) {
    }

    public function preUpdate(User $user, PreUpdateEventArgs $event): void
    {
        /** @var list<string> $changedFields */
        $changedFields = array_keys($event->getEntityChangeSet());

        if (!$this->policy->shouldTouch($changedFields)) {
            return;
        }

        $previous = $user->getLastUpdatedAt();
        $now = new DateTime();
        $user->setLastUpdatedAt($now);

        $event->getObjectManager()->getUnitOfWork()->scheduleExtraUpdate($user, [
            'lastUpdatedAt' => [$previous, $now],
        ]);
    }
}
