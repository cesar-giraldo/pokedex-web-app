<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\User;

interface ResendQuota
{
    public function allow(User $user): bool;
}
