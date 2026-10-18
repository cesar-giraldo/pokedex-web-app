<?php

declare(strict_types=1);

namespace App\Tests\Notification\Email;

use App\Entity\Enum\SupportedLanguage;
use App\Notification\Email\Exception\MissingDevMailRedirectException;
use App\Notification\Email\TransactionalEmailSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

#[CoversClass(TransactionalEmailSender::class)]
#[Group('unit')]
final class TransactionalEmailSenderTest extends TestCase
{
    public function testSendSetsFromAddressAndLocale(): void
    {
        $message = new TemplatedEmail()->to('developer@example.com')->subject('Backup')->text('Listo');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (TemplatedEmail $email): bool {
                $from = $email->getFrom()[0];

                return 'backup@example.com' === $from->getAddress()
                    && 'Pokedex' === $from->getName()
                    && 'en' === $email->getLocale()
                    && 'developer@example.com' === $email->getTo()[0]->getAddress();
            }));

        $sender = new TransactionalEmailSender($mailer, 'prod', 'backup@example.com', 'Pokedex', []);
        $sender->send($message, SupportedLanguage::English);
    }

    public function testDevWithoutRedirectDoesNotQueueTheMessage(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $sender = new TransactionalEmailSender($mailer, 'dev', 'backup@example.com', 'Pokedex', ['   ']);

        $this->expectException(MissingDevMailRedirectException::class);
        $sender->send(new TemplatedEmail()->to('developer@example.com')->text('Listo'), SupportedLanguage::Spanish);
    }

    public function testDevWithRedirectQueuesTheOriginalMessage(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (TemplatedEmail $email): bool {
                return 'developer@example.com' === $email->getTo()[0]->getAddress();
            }));

        $sender = new TransactionalEmailSender($mailer, 'dev', 'backup@example.com', 'Pokedex', ['qa@example.com']);
        $sender->send(new TemplatedEmail()->to('developer@example.com')->text('Listo'), SupportedLanguage::Spanish);
    }
}
