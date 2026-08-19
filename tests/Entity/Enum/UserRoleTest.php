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
        self::assertSame('Operador', UserRole::Operator->label());
        self::assertSame('Usuario', UserRole::User->label());
    }

    public function testOperatorGrantsBackendAccess(): void
    {
        self::assertTrue(UserRole::Operator->grantsBackendAccess());
        self::assertSame('ROLE_OPERATOR', UserRole::Operator->toSymfonyRole());
    }
}
