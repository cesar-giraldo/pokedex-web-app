<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class SvgFile extends Constraint
{
    public const int MAX_BYTES = 1048576;

    public string $extensionMessage = 'Solo se permiten imágenes SVG.';

    public string $maxSizeMessage = 'La imagen no puede superar 1 MB.';

    public string $invalidMessage = 'El archivo SVG no es válido.';
}
