<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\EmailInputComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailInputComponent::class)] #[Group('unit')]
final class EmailInputComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new EmailInputComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('email-input-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testDefaultIconAndCopyButtonFlags(): void
    {
        $component = new EmailInputComponent();

        self::assertTrue($component->showIcon);
        self::assertFalse($component->showCopyButton);
    }

    public function testInputPaddingReflectsIconAndCopyButton(): void
    {
        $default = new EmailInputComponent();
        $default->mount();
        self::assertSame('pl-[62px] pr-4', $default->getInputPaddingClass());

        $withoutIcon = new EmailInputComponent();
        $withoutIcon->showIcon = false;
        $withoutIcon->mount();
        self::assertSame('pl-4 pr-4', $withoutIcon->getInputPaddingClass());

        $withCopy = new EmailInputComponent();
        $withCopy->showCopyButton = true;
        $withCopy->mount();
        self::assertSame('pl-[62px] pr-[90px]', $withCopy->getInputPaddingClass());

        $minimal = new EmailInputComponent();
        $minimal->showIcon = false;
        $minimal->showCopyButton = true;
        $minimal->mount();
        self::assertSame('pl-4 pr-[90px]', $minimal->getInputPaddingClass());
    }

    public function testInputClassesReflectState(): void
    {
        $default = new EmailInputComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getInputClasses());

        $error = new EmailInputComponent();
        $error->error = 'Invalid email';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getInputClasses());

        $disabled = new EmailInputComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getInputClasses());
    }
}
