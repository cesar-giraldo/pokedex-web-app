<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class NicknameFormat extends Constraint
{
    public string $message = 'El nickname solo puede contener letras minúsculas, números, guiones y guiones bajos.';
}
