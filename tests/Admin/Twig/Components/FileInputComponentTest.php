<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\FileInputComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileInputComponent::class)] #[Group('unit')]
final class FileInputComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new FileInputComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('file-input-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testInputClassesReflectState(): void
    {
        $default = new FileInputComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getInputClasses());
        self::assertStringContainsString('file:bg-gray-50', $default->getInputClasses());

        $error = new FileInputComponent();
        $error->error = 'Invalid';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getInputClasses());

        $disabled = new FileInputComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getInputClasses());
    }
}
