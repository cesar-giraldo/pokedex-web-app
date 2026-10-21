<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\NotificationType;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;
use function sprintf;
use function trim;

final class DatabaseBackupStatusMailer
{
    public function __construct(
        private readonly TransactionalEmailSender $sender,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LegalLanguageResolver $legalLanguageResolver,
        private readonly GeneralSettingsProvider $generalSettingsProvider,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param list<User> $recipients
     */
    public function notify(array $recipients, NotificationType $type): void
    {
        if (!in_array($type, [NotificationType::DatabaseBackupCompleted, NotificationType::DatabaseBackupFailed], true)) {
            throw new InvalidArgumentException(sprintf('El tipo %s no corresponde a un correo de backup.', $type->value));
        }

        $succeeded = NotificationType::DatabaseBackupCompleted === $type;
        $status = $succeeded ? 'completed' : 'failed';
        $settingsUrl = $this->urlGenerator->generate(
            'app_backend_general_settings',
            referenceType: UrlGeneratorInterface::ABSOLUTE_URL,
        );

        foreach ($recipients as $recipient) {
            $emailAddress = $recipient->getEmail();

            if (null === $emailAddress || '' === trim($emailAddress)) {
                $this->logger->warning('Se omitió el correo de backup porque el usuario no tiene email.', [
                    'user_id' => $recipient->getId(),
                ]);

                continue;
            }

            $language = $this->legalLanguageResolver->preferredForUser(
                $recipient,
                $this->generalSettingsProvider->get(),
            );
            $locale = $language->value;
            $recipientName = trim($recipient->getName());

            if ('' === $recipientName) {
                $recipientName = $recipient->getNickname();
            }

            $email = new TemplatedEmail()
                ->to(new Address($emailAddress, $recipientName))
                ->subject($this->translator->trans('email.database_backup.' . $status . '.subject', locale: $locale))
                ->htmlTemplate('email/database_backup/database_backup_status.html.twig')
                ->textTemplate('email/database_backup/database_backup_status.txt.twig')
                ->context([
                    'succeeded' => $succeeded,
                    'settings_url' => $settingsUrl,
                    'recipient_name' => $recipientName,
                    'language' => $locale,
                ]);

            $this->sender->send($email, $language);
        }
    }
}
