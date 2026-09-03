<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Extensions;

use App\Admin\Twig\Extensions\FormExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

#[CoversClass(FormExtension::class)] #[Group('unit')]
final class FormExtensionTest extends TestCase
{
    private FormExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new FormExtension();
    }

    public function testGetFormFieldMaxLengthReadsMaxlengthAttribute(): void
    {
        $field = new FormView();
        $field->vars['attr'] = ['maxlength' => 100];

        self::assertSame(100, $this->extension->getFormFieldMaxLength($field));
    }

    public function testGetFormFieldMaxLengthDefaultsToZeroWhenMissing(): void
    {
        $field = new FormView();
        $field->vars['attr'] = [];

        self::assertSame(0, $this->extension->getFormFieldMaxLength($field));
    }

    public function testRegistersTwigFunction(): void
    {
        $functions = $this->extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('form_field_max_length', $functions[0]->getName());
    }
}
