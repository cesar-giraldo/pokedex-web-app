<?php

declare(strict_types=1);

namespace App\Notification;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\NotificationType;
use App\Entity\Notification;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LocalizedNotificationWriter
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly TranslatorInterface $translator,
        private readonly LegalLanguageResolver $legalLanguageResolver,
        private readonly GeneralSettingsProvider $generalSettingsProvider,
    ) {
    }

    /**
     * Persiste un título y un mensaje ya traducidos al idioma preferido de cada destinatario.
     * Quien comparte idioma recibe el mismo texto en una sola llamada.
     *
     * @param iterable<User>            $recipients
     * @param array<string, int|string> $parameters
     *
     * @return list<Notification>
     */
    public function notify(
        iterable $recipients,
        NotificationType $type,
        string $titleKey,
        string $messageKey,
        array $parameters = [],
        ?string $actionUrl = null,
    ): array {
        $recipientList = [];

        foreach ($recipients as $recipient) {
            $recipientList[] = $recipient;
        }

        if ([] === $recipientList) {
            return [];
        }

        $settings = $this->generalSettingsProvider->get();
        /** @var array<string, list<User>> $groups */
        $groups = [];

        foreach ($recipientList as $recipient) {
            $language = $this->legalLanguageResolver->preferredForUser($recipient, $settings);
            $groups[$language->value][] = $recipient;
        }

        $created = [];

        foreach ($groups as $locale => $group) {
            $batch = $this->notificationService->notify(
                recipients: $group,
                type: $type,
                title: $this->translator->trans($titleKey, $parameters, locale: $locale),
                message: $this->translator->trans($messageKey, $parameters, locale: $locale),
                actionUrl: $actionUrl,
            );

            foreach ($batch as $notification) {
                $created[] = $notification;
            }
        }

        return $created;
    }
}
