<?php

declare(strict_types=1);

namespace App\Tests\Notification\Email;

use App\Notification\Email\DevMailRedirectListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;

use function array_map;

#[CoversClass(DevMailRedirectListener::class)]
#[Group('unit')]
final class DevMailRedirectListenerTest extends TestCase
{
    public function testReplacesEnvelopeRecipientsAndKeepsTheVisibleTo(): void
    {
        $message = new TemplatedEmail()
            ->from('backup@example.com')
            ->to('developer@example.com');
        $event = new MessageEvent($message, Envelope::create($message), 'main');

        $listener = new DevMailRedirectListener([' qa@example.com ', '']);
        $listener($event);

        self::assertSame('developer@example.com', $message->getTo()[0]->getAddress());
        self::assertSame(
            ['qa@example.com'],
            array_map(static fn (Address $address): string => $address->getAddress(), $event->getEnvelope()->getRecipients()),
        );
    }

    public function testLeavesTheEnvelopeUntouchedWhenRedirectIsEmpty(): void
    {
        $message = new TemplatedEmail()
            ->from('backup@example.com')
            ->to('developer@example.com');
        $event = new MessageEvent($message, Envelope::create($message), 'main');

        $listener = new DevMailRedirectListener(['   ']);
        $listener($event);

        self::assertSame(
            ['developer@example.com'],
            array_map(static fn (Address $address): string => $address->getAddress(), $event->getEnvelope()->getRecipients()),
        );
    }
}
