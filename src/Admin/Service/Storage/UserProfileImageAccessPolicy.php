<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Admin\Security\EffectiveRoleChecker;
use App\Entity\User;

final class UserProfileImageAccessPolicy
{
    public function __construct(
        private readonly EffectiveRoleChecker $effectiveRoleChecker,
    ) {
    }

    public function canView(User $viewer, User $target): bool
    {
        if (true === $target->isHidden() && !$this->effectiveRoleChecker->isGranted('ROLE_DEVELOPER')) {
            return false;
        }

        return true;
    }
}
