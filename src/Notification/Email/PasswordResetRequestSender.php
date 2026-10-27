<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Entity\Enum\PasswordResetChannel;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\User;

interface PasswordResetRequestSender
{
    public function send(
        User $user,
        string $publicToken,
        PasswordResetChannel $channel,
        SupportedLanguage $requestLanguage,
    ): void;
}
