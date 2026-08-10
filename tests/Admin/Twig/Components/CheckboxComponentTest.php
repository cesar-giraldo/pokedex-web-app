<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\CheckboxComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckboxComponent::class)]
final class CheckboxComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new CheckboxComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('checkbox-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testLabelClassesReflectDisabledState(): void
    {
        $default = new CheckboxComponent();
        $default->mount();
        self::assertStringContainsString('cursor-pointer', $default->getLabelClasses());

        $disabled = new CheckboxComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getLabelClasses());
    }
}
