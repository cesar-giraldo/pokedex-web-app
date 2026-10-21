<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Entity\User;

interface AccountConfirmationCodeSender
{
    public function send(User $user, string $plainCode): void;
}
