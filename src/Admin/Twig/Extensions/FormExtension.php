<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FormExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('form_field_max_length', $this->getFormFieldMaxLength(...)),
        ];
    }

    public function getFormFieldMaxLength(FormView $field): int
    {
        $maxLength = $field->vars['attr']['maxlength'] ?? 0;

        return (int) $maxLength;
    }
}
