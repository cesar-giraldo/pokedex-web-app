<?php

declare(strict_types=1);

namespace App\Notification\Email;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;

use function trim;

/**
 * En dev sustituye el sobre de entrega. El encabezado To del mensaje no cambia.
 *
 * framework.mailer.envelope.recipients no acepta un env csv: el árbol de
 * configuración envuelve el placeholder y PHPStan recibe un array anidado.
 */
#[When(env: 'dev')]
#[AsEventListener(event: MessageEvent::class, priority: -256)]
final class DevMailRedirectListener
{
    /**
     * @param list<string> $redirectRecipients
     */
    public function __construct(
        #[Autowire('%env(csv:MAILER_REDIRECT_TO)%')]
        private readonly array $redirectRecipients,
    ) {
    }

    public function __invoke(MessageEvent $event): void
    {
        $recipients = $this->configuredRedirectRecipients();

        if ([] === $recipients) {
            return;
        }

        $event->getEnvelope()->setRecipients(Address::createArray($recipients));
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
