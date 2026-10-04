<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum LegalAcceptanceMethod: string
{
    case ForcedRedirect = 'forced_redirect';
    case Signup = 'signup';

    public function label(): string
    {
        return match ($this) {
            self::ForcedRedirect => 'Aceptación obligatoria',
            self::Signup => 'Registro',
        };
    }
}
