<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\SubmitButtonComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmitButtonComponent::class)] #[Group('unit')]
final class SubmitButtonComponentTest extends TestCase
{
    public function testMountKeepsDefaultProcessingLabel(): void
    {
        $component = new SubmitButtonComponent();
        $component->mount();

        self::assertSame('Procesando...', $component->processingLabel);
        self::assertSame('inline', $component->layout);
        self::assertSame('', $component->name);
        self::assertSame('', $component->id);
    }

    public function testMountRestoresEmptyProcessingLabel(): void
    {
        $component = new SubmitButtonComponent();
        $component->processingLabel = '';
        $component->mount();

        self::assertSame('Procesando...', $component->processingLabel);
    }

    public function testMountPreservesCustomProcessingLabelNameAndId(): void
    {
        $component = new SubmitButtonComponent();
        $component->processingLabel = 'Iniciando Sesión...';
        $component->name = 'general_settings_general[save]';
        $component->id = 'general_settings_general_save';
        $component->mount();

        self::assertSame('Iniciando Sesión...', $component->processingLabel);
        self::assertSame('general_settings_general[save]', $component->name);
        self::assertSame('general_settings_general_save', $component->id);
    }

    public function testMountNormalizesInvalidLayout(): void
    {
        $component = new SubmitButtonComponent();
        $component->layout = 'invalid';
        $component->mount();

        self::assertSame('inline', $component->layout);
    }

    public function testButtonClassesReflectLayout(): void
    {
        $inline = new SubmitButtonComponent();
        $inline->mount();
        self::assertStringContainsString('inline-flex', $inline->getButtonClasses());
        self::assertStringContainsString('disabled:cursor-not-allowed', $inline->getButtonClasses());
        self::assertStringNotContainsString('w-full', $inline->getButtonClasses());

        $responsive = new SubmitButtonComponent();
        $responsive->layout = 'responsive';
        $responsive->mount();
        self::assertStringContainsString('flex w-full', $responsive->getButtonClasses());
        self::assertStringContainsString('sm:w-auto', $responsive->getButtonClasses());

        $full = new SubmitButtonComponent();
        $full->layout = 'full';
        $full->mount();
        self::assertStringContainsString('flex w-full', $full->getButtonClasses());
        self::assertStringContainsString('px-4 py-3', $full->getButtonClasses());
        self::assertStringNotContainsString('sm:w-auto', $full->getButtonClasses());
    }
}
