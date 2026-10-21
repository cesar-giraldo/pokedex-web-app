<?php

declare(strict_types=1);

namespace App\Web\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function password_hash;
use function password_verify;

use const PASSWORD_BCRYPT;

final class ConfirmationCodeHasher
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function hash(string $code): string
    {
        $options = 'test' === $this->environment ? ['cost' => 4] : [];

        return password_hash($code, PASSWORD_BCRYPT, $options);
    }

    public function verify(string $code, string $hash): bool
    {
        return password_verify($code, $hash);
    }
}
