<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\TextInputComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextInputComponent::class)] #[Group('unit')]
final class TextInputComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new TextInputComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('text-input-', $component->id);
        self::assertSame($component->id, $component->name);
    }

    public function testMountNormalizesIconPatternAndCaseFlags(): void
    {
        $component = new TextInputComponent();
        $component->icon = 'magnifying-glass';
        $component->iconPosition = 'invalid';
        $component->uppercase = true;
        $component->lowercase = true;
        $component->value = 'hello';
        $component->mount();

        self::assertSame('heroicons:magnifying-glass', $component->icon);
        self::assertSame('left', $component->iconPosition);
        self::assertFalse($component->lowercase);
        self::assertSame('HELLO', $component->value);
    }

    public function testPatternForHtmlWrapsUnanchoredExpressions(): void
    {
        $component = new TextInputComponent();
        $component->pattern = '[A-Z0-9]+';
        $component->mount();

        self::assertSame('^[A-Z0-9]+$', $component->getPatternForHtml());
    }

    public function testOnlyNumbersAppliesDecimalPatternAndInputMode(): void
    {
        $component = new TextInputComponent();
        $component->onlyNumbers = true;
        $component->mount();

        self::assertSame('^-?\d+(\.\d{1,2})?$', $component->getPatternForHtml());
        self::assertSame('decimal', $component->inputMode);
        self::assertTrue($component->hasPattern());
        self::assertSame(
            'Introduce un número válido con hasta 2 decimales.',
            $component->getEffectivePatternErrorMessage()
        );
    }

    public function testOnlyNumbersTakesPrecedenceOverCustomPattern(): void
    {
        $component = new TextInputComponent();
        $component->onlyNumbers = true;
        $component->pattern = '[0-9]+';
        $component->patternErrorMessage = 'Mensaje personalizado.';
        $component->mount();

        self::assertSame('^-?\d+(\.\d{1,2})?$', $component->getPatternForHtml());
        self::assertSame('Mensaje personalizado.', $component->getEffectivePatternErrorMessage());
    }

    public function testInputPaddingReflectsIconPosition(): void
    {
        $plain = new TextInputComponent();
        $plain->mount();
        self::assertSame('pl-4 pr-4', $plain->getInputPaddingClass());

        $left = new TextInputComponent();
        $left->showIcon = true;
        $left->iconPosition = 'left';
        $left->mount();
        self::assertTrue($left->isIconLeft());
        self::assertSame('pl-[62px] pr-4', $left->getInputPaddingClass());

        $right = new TextInputComponent();
        $right->showIcon = true;
        $right->iconPosition = 'right';
        $right->mount();
        self::assertTrue($right->isIconRight());
        self::assertSame('pl-4 pr-[62px]', $right->getInputPaddingClass());
    }

    public function testInputClassesReflectState(): void
    {
        $default = new TextInputComponent();
        $default->mount();
        self::assertStringContainsString('border-gray-300', $default->getInputClasses());

        $uppercase = new TextInputComponent();
        $uppercase->uppercase = true;
        $uppercase->mount();
        self::assertStringContainsString('uppercase', $uppercase->getInputClasses());

        $error = new TextInputComponent();
        $error->error = 'Invalid value';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getInputClasses());

        $emptyError = new TextInputComponent();
        $emptyError->error = '';
        self::assertFalse($emptyError->hasError());
        self::assertStringContainsString('border-gray-300', $emptyError->getInputClasses());
        self::assertStringNotContainsString('border-error-300', $emptyError->getInputClasses());

        $disabled = new TextInputComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getInputClasses());
    }
}
