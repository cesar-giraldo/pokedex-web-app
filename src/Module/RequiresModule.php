<?php

declare(strict_types=1);

namespace App\Module;

use Attribute;

/**
 * Exige un módulo activo y, si se indica, un rol efectivo para renderizar el controlador.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequiresModule
{
    public function __construct(
        public readonly string $slug,
        public readonly ?string $role = null,
    ) {
    }
}
