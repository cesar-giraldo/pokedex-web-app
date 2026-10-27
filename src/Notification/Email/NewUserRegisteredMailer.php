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

final class NewUserRegisteredMailer
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
    public function notify(array $recipients, User $registeredUser): void
    {
        $registeredId = $registeredUser->getId();
        $profileUrl = null !== $registeredId
            ? $this->urlGenerator->generate(
                'app_backend_user_show',
                ['id' => $registeredId],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            : null;
        $registeredName = trim($registeredUser->getName() . ' ' . $registeredUser->getLastname());
        $registeredEmail = $registeredUser->getEmail() ?? '';

        foreach ($recipients as $recipient) {
            $emailAddress = $recipient->getEmail();

            if (null === $emailAddress || '' === trim($emailAddress)) {
                $this->logger->warning('Se omitió el aviso de alta porque el usuario no tiene email.', [
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
                ->subject($this->translator->trans('email.new_user.subject', locale: $locale))
                ->htmlTemplate('email/account/new_user.html.twig')
                ->textTemplate('email/account/new_user.txt.twig')
                ->context([
                    'recipient_name' => $recipientName,
                    'registered_name' => $registeredName,
                    'registered_email' => $registeredEmail,
                    'profile_url' => $profileUrl,
                    'language' => $locale,
                ]);

            $this->sender->send($email, $language);
        }
    }
}
