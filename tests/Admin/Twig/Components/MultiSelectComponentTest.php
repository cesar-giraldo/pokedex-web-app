<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\MultiSelectComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(MultiSelectComponent::class)] #[Group('unit')]
final class MultiSelectComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new MultiSelectComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('multi-select-', $component->id);
        self::assertSame(sprintf('%s[]', $component->id), $component->name);
    }

    public function testMountNormalizesSelectedValuesToStrings(): void
    {
        $component = new MultiSelectComponent();
        $component->selected = [1, 2, 'music'];
        $component->mount();

        self::assertSame(['1', '2', 'music'], $component->selected);
    }

    public function testIsSelectedComparesNormalizedValues(): void
    {
        $component = new MultiSelectComponent();
        $component->selected = ['tech', 'music'];
        $component->mount();

        self::assertTrue($component->isSelected('tech'));
        self::assertFalse($component->isSelected('sports'));
    }

    public function testTriggerClassesReflectState(): void
    {
        $default = new MultiSelectComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getTriggerClasses());

        $error = new MultiSelectComponent();
        $error->error = 'Invalid';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getTriggerClasses());

        $disabled = new MultiSelectComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getTriggerClasses());
    }
}
