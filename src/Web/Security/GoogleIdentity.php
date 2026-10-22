<?php

declare(strict_types=1);

namespace App\Web\Security;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $sub,
        public string $email,
        public string $givenName,
        public string $familyName,
    ) {
    }
}
