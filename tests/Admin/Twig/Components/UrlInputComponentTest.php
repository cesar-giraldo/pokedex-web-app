<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\UrlInputComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UrlInputComponent::class)]
final class UrlInputComponentTest extends TestCase
{
    public function testMountGeneratesIdAndNameWhenMissing(): void
    {
        $component = new UrlInputComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('url-input-', $component->id);
        self::assertSame($component->id, $component->name);
        self::assertSame('http://', $component->prefix);
    }

    public function testMountUsesDefaultPrefixWhenEmpty(): void
    {
        $component = new UrlInputComponent();
        $component->prefix = '';
        $component->mount();

        self::assertSame('http://', $component->prefix);
    }

    public function testDisplayValueStripsKnownPrefix(): void
    {
        $component = new UrlInputComponent();
        $component->value = 'http://www.tailadmin.com';
        $component->mount();

        self::assertSame('www.tailadmin.com', $component->getDisplayValue());
    }

    public function testDisplayValueStripsProtocolWhenDifferentPrefix(): void
    {
        $component = new UrlInputComponent();
        $component->prefix = 'https://';
        $component->value = 'http://www.example.com/path';
        $component->mount();

        self::assertSame('www.example.com/path', $component->getDisplayValue());
    }

    public function testInputClassesReflectStateAndPrefixLength(): void
    {
        $default = new UrlInputComponent();
        $default->mount();
        self::assertStringContainsString('pl-[90px]', $default->getInputClasses());
        self::assertStringContainsString('border-gray-300', $default->getInputClasses());

        $https = new UrlInputComponent();
        $https->prefix = 'https://';
        $https->mount();
        self::assertSame('pl-[94px]', $https->getInputPaddingClass());

        $error = new UrlInputComponent();
        $error->error = 'Invalid URL';
        $error->mount();
        self::assertStringContainsString('border-error-300', $error->getInputClasses());

        $disabled = new UrlInputComponent();
        $disabled->disabled = true;
        $disabled->mount();
        self::assertStringContainsString('cursor-not-allowed', $disabled->getInputClasses());
    }
}
