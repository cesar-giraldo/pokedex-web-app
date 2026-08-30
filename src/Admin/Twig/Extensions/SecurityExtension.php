<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use App\Admin\Security\EffectiveRoleChecker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SecurityExtension extends AbstractExtension
{
    public function __construct(
        private readonly EffectiveRoleChecker $effectiveRoleChecker,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('effective_is_granted', $this->effectiveRoleChecker->isGranted(...)),
            new TwigFunction('is_impersonating', $this->effectiveRoleChecker->isImpersonating(...)),
        ];
    }
}
