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

    public function label(): string
    {
        return match ($this) {
            self::UnconfirmedAccount => 'Cuenta sin confirmar',
            self::UncompleteProfileInfo => 'Perfil incompleto',
            self::Active => 'Activo',
            self::Banned => 'Suspendido',
            self::Inactive => 'Inactivo',
        };
    }

    public function listBadgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
            self::UncompleteProfileInfo, self::UnconfirmedAccount => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
            self::Inactive => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-white/80',
            self::Banned => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-500',
        };
    }
}
