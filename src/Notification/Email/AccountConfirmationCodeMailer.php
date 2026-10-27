<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function trim;

final class AccountConfirmationCodeMailer implements AccountConfirmationCodeSender
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

    public function send(User $user, string $plainCode): void
    {
        $emailAddress = $user->getEmail();

        if (null === $emailAddress || '' === trim($emailAddress)) {
            $this->logger->warning('Se omitió el código de confirmación porque el usuario no tiene email.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        $language = $this->legalLanguageResolver->preferredForUser(
            $user,
            $this->generalSettingsProvider->get(),
        );
        $confirmUrl = $this->urlGenerator->generate(
            'app_public_account_confirm',
            ['_locale' => $language->value],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $recipientName = trim($user->getName());

        if ('' === $recipientName) {
            $recipientName = $emailAddress;
        }

        $email = new TemplatedEmail()
            ->to(new Address($emailAddress, $recipientName))
            ->subject($this->translator->trans('email.confirmation_code.subject', locale: $language->value))
            ->htmlTemplate('email/account/confirmation_code.html.twig')
            ->textTemplate('email/account/confirmation_code.txt.twig')
            ->context([
                'recipient_name' => $recipientName,
                'code' => $plainCode,
                'confirm_url' => $confirmUrl,
                'nickname' => $user->getNickname(),
                'language' => $language->value,
            ]);

        $this->sender->send($email, $language);
    }
}
