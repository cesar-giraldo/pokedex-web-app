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
use Throwable;

use function trim;

final class WelcomeMailer
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

    public function send(User $user): void
    {
        $emailAddress = $user->getEmail();

        if (null === $emailAddress || '' === trim($emailAddress)) {
            $this->logger->warning('Se omitió el correo de bienvenida porque el usuario no tiene email.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        try {
            $language = $this->legalLanguageResolver->preferredForUser(
                $user,
                $this->generalSettingsProvider->get(),
            );
            $homeUrl = $this->urlGenerator->generate(
                'app_public_home',
                ['_locale' => $language->value],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $recipientName = trim($user->getName());

            if ('' === $recipientName) {
                $recipientName = $user->getNickname();
            }

            $email = new TemplatedEmail()
                ->to(new Address($emailAddress, $recipientName))
                ->subject($this->translator->trans('email.welcome.subject', locale: $language->value))
                ->htmlTemplate('email/account/welcome.html.twig')
                ->textTemplate('email/account/welcome.txt.twig')
                ->context([
                    'recipient_name' => $recipientName,
                    'home_url' => $homeUrl,
                    'language' => $language->value,
                ]);

            $this->sender->send($email, $language);
        } catch (Throwable $exception) {
            $this->logger->error('No se pudo enviar el correo de bienvenida.', [
                'user_id' => $user->getId(),
                'exception' => $exception,
            ]);
        }
    }
}
