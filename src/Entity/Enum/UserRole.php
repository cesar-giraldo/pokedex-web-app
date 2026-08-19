<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use function is_string;

enum UserRole: string
{
    case Developer = 'developer';
    case Admin = 'admin';
    case Operator = 'operator';
    case User = 'user';

    public function toSymfonyRole(): string
    {
        return match ($this) {
            self::Developer => 'ROLE_DEVELOPER',
            self::Admin => 'ROLE_ADMIN',
            self::Operator => 'ROLE_OPERATOR',
            self::User => 'ROLE_USER',
        };
    }

    public function grantsBackendAccess(): bool
    {
        return match ($this) {
            self::Developer, self::Admin, self::Operator => true,
            self::User => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Developer => 'Developer',
            self::Admin => 'Admin',
            self::Operator => 'Operador',
            self::User => 'Usuario',
        };
    }

    /**
     * @param array<mixed> $values
     *
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
