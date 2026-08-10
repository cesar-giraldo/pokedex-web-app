<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\PhoneInputComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhoneInputComponent::class)]
final class PhoneInputComponentTest extends TestCase
{
    public function testMountGeneratesIdNameAndCountryNameWhenMissing(): void
    {
        $component = new PhoneInputComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('phone-input-', $component->id);
        self::assertSame($component->id, $component->name);
        self::assertSame(sprintf('%s_country', $component->name), $component->countryName);
    }

    public function testMountNormalizesCountrySelectorPosition(): void
    {
        $component = new PhoneInputComponent();
        $component->countrySelectorPosition = 'invalid';
        $component->mount();

        self::assertSame('left', $component->countrySelectorPosition);
    }

    public function testMountUsesDefaultFormatRegexWhenEmpty(): void
    {
        $component = new PhoneInputComponent();
        $component->formatRegex = '';
        $component->mount();

        self::assertSame(PhoneInputComponent::DEFAULT_FORMAT_REGEX, $component->formatRegex);
    }

    public function testInputClassesReflectPositionAndState(): void
    {
        $left = new PhoneInputComponent();
        $left->mount();
        self::assertStringContainsString('pl-[84px]', $left->getInputClasses());
        self::assertTrue($left->isCountrySelectorLeft());

        $right = new PhoneInputComponent();
        $right->countrySelectorPosition = 'right';
        $right->mount();
        self::assertStringContainsString('pr-[84px]', $right->getInputClasses());
        self::assertFalse($right->isCountrySelectorLeft());

        $error = new PhoneInputComponent();
        $error->error = 'Invalid';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getInputClasses());
    }
}
