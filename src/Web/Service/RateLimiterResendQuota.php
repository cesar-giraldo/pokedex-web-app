<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function spl_object_id;

final class RateLimiterResendQuota implements ResendQuota
{
    public function __construct(
        #[Autowire(service: 'limiter.account_confirmation_resend')]
        private readonly RateLimiterFactory $limiter,
    ) {
    }

    public function hasAllowance(User $user): bool
    {
        return $this->limiter->create($this->key($user))->consume(0)->getRemainingTokens() > 0;
    }

    public function consume(User $user): void
    {
        $this->limiter->create($this->key($user))->consume();
    }

    private function key(User $user): string
    {
        return null !== $user->getId() ? (string) $user->getId() : 'obj-' . spl_object_id($user);
    }
}
