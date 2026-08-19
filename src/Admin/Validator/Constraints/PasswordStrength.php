<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class PasswordStrength extends Constraint
{
    public string $message = 'La contraseña debe tener al menos 5 caracteres e incluir letras y números.';
}
