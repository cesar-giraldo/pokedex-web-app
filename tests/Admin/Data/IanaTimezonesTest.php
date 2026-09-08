<?php

declare(strict_types=1);

namespace App\Tests\Admin\Data;

use App\Admin\Data\IanaTimezones;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class IanaTimezonesTest extends TestCase
{
    public function testIncludesAmericaBogota(): void
    {
        self::assertTrue(IanaTimezones::exists('America/Bogota'));
        self::assertArrayHasKey('America/Bogota', IanaTimezones::options());
        self::assertSame('America/Bogota', IanaTimezones::choices()[IanaTimezones::label('America/Bogota')]);
    }

    public function testLabelIncludesOffsetAndIdentifier(): void
    {
        $label = IanaTimezones::label('America/Bogota');

        self::assertStringContainsString('America/Bogota', $label);
        self::assertStringContainsString('UTC', $label);
    }
}
