<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\TextareaComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextareaComponent::class)] #[Group('unit')]
final class TextareaComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new TextareaComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('textarea-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testMountNormalizesRowsToMinimumOfOne(): void
    {
        $component = new TextareaComponent();
        $component->rows = 0;
        $component->mount();

        self::assertSame(1, $component->rows);
    }

    public function testTextareaClassesReflectState(): void
    {
        $default = new TextareaComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getTextareaClasses());

        $error = new TextareaComponent();
        $error->error = 'Invalid';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getTextareaClasses());

        $disabled = new TextareaComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('disabled:bg-gray-50', $disabled->getTextareaClasses());
    }
}
