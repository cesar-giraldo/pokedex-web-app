<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Components;

use App\Admin\Twig\Components\ConfirmDialogComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfirmDialogComponent::class)]
#[Group('unit')]
final class ConfirmDialogComponentTest extends TestCase
{
    public function testMountGeneratesIdWhenMissing(): void
    {
        $component = new ConfirmDialogComponent();
        $component->mount();

        self::assertNotSame('', $component->id);
        self::assertStringStartsWith('confirm-dialog-', $component->id);
    }

    public function testDefaultLabels(): void
    {
        $component = new ConfirmDialogComponent();

        self::assertSame('Confirmar', $component->title);
        self::assertSame('Cancelar', $component->cancelLabel);
        self::assertSame('Eliminar', $component->confirmLabel);
    }
}
