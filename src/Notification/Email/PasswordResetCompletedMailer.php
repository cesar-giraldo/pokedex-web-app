<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Entity\Enum\PasswordResetChannel;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\User;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;
use function trim;

final class PasswordResetCompletedMailer implements PasswordResetCompletedSender
{
    public function __construct(
        private readonly TransactionalEmailSender $sender,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PasswordResetLanguage $language,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function send(
        User $user,
        PasswordResetChannel $channel,
        DateTimeInterface $changedAt,
        SupportedLanguage $requestLanguage,
    ): void {
        $emailAddress = $user->getEmail();

        if (null === $emailAddress || '' === trim($emailAddress)) {
            $this->logger->warning('Se omitió el aviso de contraseña actualizada porque el usuario no tiene email.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        $resolved = $this->language->resolve($user, $requestLanguage);
        $recipientName = $this->language->recipientName($user, $emailAddress);
        $contactUrl = null;

        if (PasswordResetChannel::Web === $channel) {
            $contactUrl = $this->urlGenerator->generate(
                'app_public_contact',
                ['_locale' => $resolved->value],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress, $recipientName))
            ->subject($this->translator->trans('email.password_reset_completed.subject', locale: $resolved->value))
            ->htmlTemplate('email/account/password_reset_completed.html.twig')
            ->textTemplate('email/account/password_reset_completed.txt.twig')
            ->context([
                'recipient_name' => $recipientName,
                'changed_at' => $this->formatChangedAt($user, $changedAt),
                'channel' => $channel->value,
                'contact_url' => $contactUrl,
                'contact_email' => $this->translator->trans('contact.email', locale: $resolved->value),
                'language' => $resolved->value,
            ]);

        $this->sender->send($email, $resolved);
    }

    private function formatChangedAt(User $user, DateTimeInterface $changedAt): string
    {
        try {
            $timezone = new DateTimeZone($user->getTimezone());
        } catch (Exception) {
            $timezone = new DateTimeZone('UTC');
        }

        $formatter = new IntlDateFormatter(
            $user->getLocale()->intlLocale(),
            IntlDateFormatter::LONG,
            IntlDateFormatter::SHORT,
            $timezone,
        );
        $formatted = $formatter->format($changedAt);

        return is_string($formatted) && '' !== $formatted ? $formatted : $changedAt->format('Y-m-d H:i');
    }
}
