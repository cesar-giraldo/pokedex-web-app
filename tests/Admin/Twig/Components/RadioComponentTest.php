<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\RadioComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(RadioComponent::class)] #[Group('unit')]
final class RadioComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new RadioComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('radio-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testLabelClassesReflectDisabledState(): void
    {
        $default = new RadioComponent();
        $default->mount();
        self::assertStringContainsString('cursor-pointer', $default->getLabelClasses());

        $disabled = new RadioComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getLabelClasses());
    }
}
