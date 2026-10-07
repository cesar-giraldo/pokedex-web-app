<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\RichTextComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(RichTextComponent::class)]
#[Group('unit')]
final class RichTextComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new RichTextComponent();
        $component->mount();

        self::assertStringStartsWith('rich-text-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testLabelClassesReflectDisabledState(): void
    {
        $disabled = new RichTextComponent();
        $disabled->disabled = true;
        $disabled->mount();

        self::assertStringContainsString('text-gray-300', $disabled->getLabelClasses());
    }
}
