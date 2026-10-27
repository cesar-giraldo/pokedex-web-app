<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PasswordResetChannel: string
{
    case Web = 'web';
    case Admin = 'admin';
}
