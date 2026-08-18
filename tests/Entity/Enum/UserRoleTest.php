<?php

declare(strict_types=1);

namespace App\Tests\Entity\Enum;

use App\Entity\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    public function testLabelsAreDefinedInSpanish(): void
    {
        self::assertSame('Developer', UserRole::Developer->label());
        self::assertSame('Admin', UserRole::Admin->label());
        self::assertSame('Usuario', UserRole::User->label());
    }
}
