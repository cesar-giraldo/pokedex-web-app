<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum NotificationType: string
{
    case SystemInfo = 'system.info';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case DatabaseBackupCompleted = 'database_backup.completed';
    case DatabaseBackupFailed = 'database_backup.failed';
    case PokemonsImported = 'pokemons.imported';

    public function label(): string
    {
        return match ($this) {
            self::SystemInfo => 'Sistema',
            self::UserCreated, self::UserUpdated => 'Usuarios',
            self::DatabaseBackupCompleted, self::DatabaseBackupFailed => 'Base de datos',
            self::PokemonsImported => 'Pokémon',
        };
    }
}
