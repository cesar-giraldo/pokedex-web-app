<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use function is_string;

enum UserRole: string
{
    case Developer = 'developer';
    case Admin = 'admin';
    case User = 'user';

    public function toSymfonyRole(): string
    {
        return match ($this) {
            self::Developer => 'ROLE_DEVELOPER',
            self::Admin => 'ROLE_ADMIN',
            self::User => 'ROLE_USER',
        };
    }

    public function grantsBackendAccess(): bool
    {
        return match ($this) {
            self::Developer, self::Admin => true,
            self::User => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function fromStoredValues(array $values): array
    {
        $roles = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $role = self::tryFrom($value);

            if (null !== $role) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
