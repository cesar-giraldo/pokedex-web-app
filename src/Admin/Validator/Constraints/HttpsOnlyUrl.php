<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class HttpsOnlyUrl extends Constraint
{
    public string $message = 'Introduce una URL válida que comience con https://';
}
