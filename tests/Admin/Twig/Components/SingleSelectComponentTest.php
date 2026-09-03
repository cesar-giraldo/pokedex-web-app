<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\SingleSelectComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(SingleSelectComponent::class)] #[Group('unit')]
final class SingleSelectComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new SingleSelectComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('single-select-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testMountNormalizesSelectedValueToString(): void
    {
        $component = new SingleSelectComponent();
        $component->selected = 'marketing';
        $component->mount();

        self::assertSame('marketing', $component->selected);
    }

    public function testSelectClassesReflectState(): void
    {
        $default = new SingleSelectComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getSelectClasses());

        $error = new SingleSelectComponent();
        $error->error = 'Invalid';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getSelectClasses());

        $disabled = new SingleSelectComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getSelectClasses());
    }
}
