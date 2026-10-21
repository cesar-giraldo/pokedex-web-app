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

    public function allow(User $user): bool
    {
        $key = null !== $user->getId() ? (string) $user->getId() : 'obj-' . spl_object_id($user);

        return $this->limiter->create($key)->consume()->isAccepted();
    }
}
