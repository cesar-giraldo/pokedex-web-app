<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Entity\User;
use App\Notification\Email\AccountConfirmationCodeSender;

final class CapturingConfirmationCodeSender implements AccountConfirmationCodeSender
{
    public static ?string $lastCode = null;

    public function __construct(
        private readonly AccountConfirmationCodeSender $inner,
    ) {
    }

    public function send(User $user, string $plainCode): void
    {
        self::$lastCode = $plainCode;
        $this->inner->send($user, $plainCode);
    }
}
