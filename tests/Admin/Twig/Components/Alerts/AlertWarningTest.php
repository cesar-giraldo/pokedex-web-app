<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components\Alerts;

use App\Admin\Twig\Components\Alerts\AlertWarning;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertWarning::class)] #[Group('unit')]
final class AlertWarningTest extends TestCase
{
    public function testMountGeneratesIdWhenMissing(): void
    {
        $component = new AlertWarning();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('alert-warning-', $component->id);
    }

    public function testMountNormalizesNegativeAutoHideDelay(): void
    {
        $component = new AlertWarning();
        $component->autoHideDelay = -100;
        $component->mount();

        self::assertSame(0, $component->autoHideDelay);
    }

    public function testDefaultConfiguration(): void
    {
        $component = new AlertWarning();

        self::assertSame(5000, $component->autoHideDelay);
        self::assertTrue($component->dismissible);
    }
}
