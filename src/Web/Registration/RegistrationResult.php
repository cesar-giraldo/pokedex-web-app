<?php

declare(strict_types=1);

namespace App\Web\Registration;

use App\Entity\User;

final readonly class RegistrationResult
{
    public function __construct(
        public User $user,
        public bool $emailSent,
    ) {
    }
}
