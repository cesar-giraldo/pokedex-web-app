<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\TimePickerComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimePickerComponent::class)] #[Group('unit')]
final class TimePickerComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new TimePickerComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('time-picker-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testInputClassesReflectState(): void
    {
        $default = new TimePickerComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getInputClasses());

        $error = new TimePickerComponent();
        $error->error = 'Invalid';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getInputClasses());

        $disabled = new TimePickerComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getInputClasses());
    }
}
