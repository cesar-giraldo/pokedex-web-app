<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components\Alerts;

use App\Admin\Twig\Components\Alerts\AlertInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertInfo::class)] #[Group('unit')]
final class AlertInfoTest extends TestCase
{
    public function testMountGeneratesIdWhenMissing(): void
    {
        $component = new AlertInfo();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('alert-info-', $component->id);
    }

    public function testMountNormalizesNegativeAutoHideDelay(): void
    {
        $component = new AlertInfo();
        $component->autoHideDelay = -100;
        $component->mount();

        self::assertSame(0, $component->autoHideDelay);
    }

    public function testDefaultConfiguration(): void
    {
        $component = new AlertInfo();

        self::assertSame(5000, $component->autoHideDelay);
        self::assertTrue($component->dismissible);
    }
}
