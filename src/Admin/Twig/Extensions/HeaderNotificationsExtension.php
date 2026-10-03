<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use App\Entity\Notification;
use App\Entity\User;
use App\Notification\NotificationService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class HeaderNotificationsExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('header_notifications', $this->headerNotifications(...)),
        ];
    }

    /**
     * @return array{unreadCount: int, items: list<Notification>}
     */
    public function headerNotifications(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return [
                'unreadCount' => 0,
                'items' => [],
            ];
        }

        return [
            'unreadCount' => $this->notificationService->countUnread($user),
            'items' => $this->notificationService->listForUser($user, 8),
        ];
    }
}
