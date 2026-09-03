<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\PasswordInputComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordInputComponent::class)] #[Group('unit')]
final class PasswordInputComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new PasswordInputComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('password-input-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testInputClassesReflectState(): void
    {
        $default = new PasswordInputComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getInputClasses());

        $error = new PasswordInputComponent();
        $error->error = 'Invalid';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getInputClasses());

        $disabled = new PasswordInputComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getInputClasses());
    }
}
