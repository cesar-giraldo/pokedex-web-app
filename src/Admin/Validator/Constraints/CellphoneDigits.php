<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class CellphoneDigits extends Constraint
{
    public string $message = 'El celular solo puede contener dígitos.';
}
