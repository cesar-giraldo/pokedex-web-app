<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Entity\Enum\PasswordResetChannel;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function trim;

final class PasswordResetRequestMailer implements PasswordResetRequestSender
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
        string $publicToken,
        PasswordResetChannel $channel,
        SupportedLanguage $requestLanguage,
    ): void {
        $emailAddress = $user->getEmail();

        if (null === $emailAddress || '' === trim($emailAddress)) {
            $this->logger->warning('Se omitió el enlace de recuperación porque el usuario no tiene email.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        $resolved = $this->language->resolve($user, $requestLanguage);
        $recipientName = $this->language->recipientName($user, $emailAddress);
        $resetUrl = $this->resetUrl($publicToken, $channel, $resolved);

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress, $recipientName))
            ->subject($this->translator->trans('email.password_reset.subject', locale: $resolved->value))
            ->htmlTemplate('email/account/password_reset.html.twig')
            ->textTemplate('email/account/password_reset.txt.twig')
            ->context([
                'recipient_name' => $recipientName,
                'reset_url' => $resetUrl,
                'language' => $resolved->value,
            ]);

        $this->sender->send($email, $resolved);
    }

    private function resetUrl(string $publicToken, PasswordResetChannel $channel, SupportedLanguage $language): string
    {
        if (PasswordResetChannel::Admin === $channel) {
            return $this->urlGenerator->generate(
                'app_admin_reset_password',
                ['token' => $publicToken],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        return $this->urlGenerator->generate(
            'app_public_reset_password',
            [
                '_locale' => $language->value,
                'token' => $publicToken,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
