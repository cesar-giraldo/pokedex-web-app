<?php

declare(strict_types=1);

namespace App\Tests\Entity\Enum;

use App\Entity\Enum\UserStatus;
use PHPUnit\Framework\TestCase;

final class UserStatusTest extends TestCase
{
    public function testLabelsAreDefinedInSpanish(): void
    {
        self::assertSame('Cuenta sin confirmar', UserStatus::UnconfirmedAccount->label());
        self::assertSame('Perfil incompleto', UserStatus::UncompleteProfileInfo->label());
        self::assertSame('Activo', UserStatus::Active->label());
        self::assertSame('Suspendido', UserStatus::Banned->label());
        self::assertSame('Inactivo', UserStatus::Inactive->label());
    }
}
