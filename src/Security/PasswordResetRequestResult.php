<?php

declare(strict_types=1);

namespace App\Security;

final readonly class PasswordResetRequestResult
{
    private function __construct(public bool $rateLimitedByIp)
    {
    }

    public static function accepted(): self
    {
        return new self(false);
    }

    public static function rateLimited(): self
    {
        return new self(true);
    }
}
