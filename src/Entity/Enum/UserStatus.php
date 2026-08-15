<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum UserStatus: string
{
    case UnconfirmedAccount = 'unconfirmed_account';
    case UncompleteProfileInfo = 'uncomplete_profile_info';
    case Active = 'active';
    case Banned = 'banned';
    case Inactive = 'inactive';

    public function allowsBackendLogin(): bool
    {
        return match ($this) {
            self::Active, self::UncompleteProfileInfo => true,
            self::UnconfirmedAccount, self::Banned, self::Inactive => false,
        };
    }

    public function loginDeniedMessage(): string
    {
        return match ($this) {
            self::UnconfirmedAccount => 'Debes confirmar tu cuenta antes de iniciar sesión.',
            self::Banned => 'Tu cuenta ha sido suspendida. Contacta al administrador.',
            self::Inactive => 'Tu cuenta está inactiva. Contacta al administrador.',
            default => 'No puedes iniciar sesión en este momento.',
        };
    }
}
