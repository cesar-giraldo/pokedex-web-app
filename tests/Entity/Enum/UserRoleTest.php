<?php

declare(strict_types=1);

namespace App\Tests\Entity\Enum;

use App\Entity\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    public function testPrimaryFromRolesReturnsHighestHierarchyRole(): void
    {
        $primary = UserRole::primaryFromRoles([
            UserRole::Operator,
            UserRole::Developer,
            UserRole::Admin,
        ]);

        self::assertSame(UserRole::Developer, $primary);
    }
}
