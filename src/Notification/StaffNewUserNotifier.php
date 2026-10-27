<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\User;

interface StaffNewUserNotifier
{
    public function notify(User $registeredUser): void;
}
