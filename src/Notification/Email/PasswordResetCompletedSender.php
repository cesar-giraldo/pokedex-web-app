<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Entity\Enum\PasswordResetChannel;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\User;
use DateTimeInterface;

interface PasswordResetCompletedSender
{
    public function send(
        User $user,
        PasswordResetChannel $channel,
        DateTimeInterface $changedAt,
        SupportedLanguage $requestLanguage,
    ): void;
}
