<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Enum\NotificationType;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Notification\Email\NewUserRegisteredMailer;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function trim;

final class NewUserRegisteredNotifier implements StaffNewUserNotifier
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly LocalizedNotificationWriter $notifications,
        private readonly NewUserRegisteredMailer $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function notify(User $registeredUser): void
    {
        try {
            $recipients = $this->users->findByRoles(
                roles: [UserRole::Admin, UserRole::Developer],
                status: UserStatus::Active,
                excludeHidden: true,
            );

            if ([] === $recipients) {
                return;
            }

            $registeredId = $registeredUser->getId();
            $actionUrl = null !== $registeredId
                ? $this->urlGenerator->generate('app_backend_user_show', ['id' => $registeredId])
                : null;
            $name = trim($registeredUser->getName() . ' ' . $registeredUser->getLastname());

            $this->notifications->notify(
                recipients: $recipients,
                type: NotificationType::UserCreated,
                titleKey: 'notifications.user_registered.title',
                messageKey: 'notifications.user_registered.message',
                parameters: [
                    'name' => '' !== $name ? $name : $registeredUser->getNickname(),
                    'email' => $registeredUser->getEmail() ?? '',
                ],
                actionUrl: $actionUrl,
            );
            $this->mailer->notify($recipients, $registeredUser);
        } catch (Throwable $exception) {
            $this->logger->error('No se pudo avisar el alta de un usuario.', [
                'user_id' => $registeredUser->getId(),
                'exception' => $exception,
            ]);
        }
    }
}
