<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\DatePickerComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatePickerComponent::class)] #[Group('unit')]
final class DatePickerComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new DatePickerComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('date-picker-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testInputClassesReflectState(): void
    {
        $default = new DatePickerComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getInputClasses());

        $error = new DatePickerComponent();
        $error->error = 'Invalid';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getInputClasses());

        $disabled = new DatePickerComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getInputClasses());
    }
}
