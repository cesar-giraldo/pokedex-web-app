<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components\Alerts;

use App\Admin\Twig\Components\Alerts\AlertSuccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertSuccess::class)] #[Group('unit')]
final class AlertSuccessTest extends TestCase
{
    public function testMountGeneratesIdWhenMissing(): void
    {
        $component = new AlertSuccess();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('alert-success-', $component->id);
    }

    public function testMountNormalizesNegativeAutoHideDelay(): void
    {
        $component = new AlertSuccess();
        $component->autoHideDelay = -100;
        $component->mount();

        self::assertSame(0, $component->autoHideDelay);
    }

    public function testDefaultConfiguration(): void
    {
        $component = new AlertSuccess();

        self::assertSame(5000, $component->autoHideDelay);
        self::assertTrue($component->dismissible);
    }
}
