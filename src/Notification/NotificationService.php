<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Enum\NotificationType;
use App\Entity\Notification;
use App\Entity\User;
use App\Notification\Exception\NotificationAccessDeniedException;
use App\Repository\NotificationRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

use function spl_object_id;
use function trim;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    /**
     * @param User|iterable<User>       $recipients
     * @param array<string, mixed>|null $payload
     *
     * @return list<Notification>
     */
    public function notify(
        User|iterable $recipients,
        NotificationType $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?User $actor = null,
        ?array $payload = null,
    ): array {
        $title = trim($title);
        $message = trim($message);

        if ('' === $title || '' === $message) {
            throw new InvalidArgumentException('La notificación requiere título y mensaje.');
        }

        $created = [];
        $seen = [];

        foreach ($this->normalizeRecipients($recipients) as $recipient) {
            $key = null !== $recipient->getId()
                ? 'id-' . $recipient->getId()
                : 'obj-' . spl_object_id($recipient);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $notification = new Notification($recipient, $type, $title, $message);
            $notification->setActionUrl($actionUrl);
            $notification->setActor($actor);
            $notification->setPayload($payload);

            $this->entityManager->persist($notification);
            $created[] = $notification;
        }

        if ([] !== $created) {
            $this->entityManager->flush();
        }

        return $created;
    }

    public function countUnread(User $user): int
    {
        return $this->notificationRepository->countUnreadFor($user);
    }

    /**
     * @return list<Notification>
     */
    public function listForUser(User $user, int $limit = 10): array
    {
        return $this->notificationRepository->findRecentFor($user, $limit);
    }

    public function markAsRead(Notification $notification, User $viewer): void
    {
        if ($notification->getRecipient()->getId() !== $viewer->getId()) {
            throw new NotificationAccessDeniedException('La notificación no pertenece al usuario.');
        }

        if ($notification->isRead()) {
            return;
        }

        $notification->markAsRead(new DateTime());
        $this->entityManager->flush();
    }

    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsRead($user, new DateTime());
    }

    /**
     * @param User|iterable<User> $recipients
     *
     * @return list<User>
     */
    private function normalizeRecipients(User|iterable $recipients): array
    {
        if ($recipients instanceof User) {
            return [$recipients];
        }

        $normalized = [];

        foreach ($recipients as $recipient) {
            $normalized[] = $recipient;
        }

        return $normalized;
    }
}
