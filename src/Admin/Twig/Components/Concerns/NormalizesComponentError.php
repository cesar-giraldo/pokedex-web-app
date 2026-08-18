<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components\Concerns;

use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Evalúa errores de servidor ignorando cadenas vacías de macros como field_error().
 */
trait NormalizesComponentError
{
    #[ExposeInTemplate('hasError')]
    public function hasError(): bool
    {
        return null !== $this->error && '' !== trim($this->error);
    }
}
