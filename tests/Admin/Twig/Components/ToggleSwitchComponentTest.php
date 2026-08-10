<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\ToggleSwitchComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToggleSwitchComponent::class)]
final class ToggleSwitchComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new ToggleSwitchComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('toggle-switch-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testMountNormalizesVariant(): void
    {
        $component = new ToggleSwitchComponent();
        $component->variant = 'invalid';
        $component->mount();

        self::assertSame('brand', $component->variant);
    }

    public function testLabelClassesReflectDisabledState(): void
    {
        $default = new ToggleSwitchComponent();
        $default->mount();
        self::assertStringContainsString('cursor-pointer', $default->getLabelClasses());

        $disabled = new ToggleSwitchComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getLabelClasses());
        self::assertStringContainsString('text-gray-400', $disabled->getLabelClasses());
    }
}
