<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\DropzoneComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DropzoneComponent::class)]
final class DropzoneComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new DropzoneComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('dropzone-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testMaxFileSizeBytesIsCalculatedFromKilobytes(): void
    {
        $component = new DropzoneComponent();
        $component->maxFileSizeKb = 5120;
        $component->mount();

        self::assertSame(5242880, $component->getMaxFileSizeBytes());
    }

    public function testZoneClassesReflectState(): void
    {
        $default = new DropzoneComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300!', $default->getZoneClasses());

        $error = new DropzoneComponent();
        $error->error = 'Too large';
        $error->mount();
        self::assertStringContainsString('border-error-300!', $error->getZoneClasses());

        $disabled = new DropzoneComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getZoneClasses());
    }
}
