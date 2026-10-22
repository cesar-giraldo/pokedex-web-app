<?php

declare(strict_types=1);

namespace App\Web\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class AvailableAccountIdentity extends Constraint
{
    public string $message = 'auth.register_unavailable';
}
