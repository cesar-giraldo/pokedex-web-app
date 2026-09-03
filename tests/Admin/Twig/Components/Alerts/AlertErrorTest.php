<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components\Alerts;

use App\Admin\Twig\Components\Alerts\AlertError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertError::class)] #[Group('unit')]
final class AlertErrorTest extends TestCase
{
    public function testMountGeneratesIdWhenMissing(): void
    {
        $component = new AlertError();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('alert-error-', $component->id);
    }

    public function testMountNormalizesNegativeAutoHideDelay(): void
    {
        $component = new AlertError();
        $component->autoHideDelay = -100;
        $component->mount();

        self::assertSame(0, $component->autoHideDelay);
    }

    public function testDefaultConfiguration(): void
    {
        $component = new AlertError();

        self::assertSame(5000, $component->autoHideDelay);
        self::assertTrue($component->dismissible);
    }
}
