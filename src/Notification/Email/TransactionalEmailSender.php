<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Entity\Enum\SupportedLanguage;
use App\Notification\Email\Exception\MissingDevMailRedirectException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

use function trim;

final class TransactionalEmailSender
{
    /**
     * @param list<string> $redirectRecipients
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        private readonly string $fromAddress,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private readonly string $fromName,
        #[Autowire('%env(csv:MAILER_REDIRECT_TO)%')]
        private readonly array $redirectRecipients,
    ) {
    }

    public function send(TemplatedEmail $email, SupportedLanguage $language): void
    {
        if ('dev' === $this->environment && [] === $this->configuredRedirectRecipients()) {
            throw new MissingDevMailRedirectException('MAILER_REDIRECT_TO es obligatorio para encolar correos en el entorno dev.');
        }

        $email
            ->from(new Address($this->fromAddress, $this->fromName))
            ->locale($language->value);

        $this->mailer->send($email);
    }

    /**
     * @return list<string>
     */
    private function configuredRedirectRecipients(): array
    {
        $recipients = [];

        foreach ($this->redirectRecipients as $recipient) {
            $recipient = trim($recipient);

            if ('' !== $recipient) {
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }
}
