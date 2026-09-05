<?php

declare(strict_types=1);

namespace App\Doctrine;

use function in_array;

/**
 * lastUpdatedAt tracks human edits to user profile/record data, not auth or password writes.
 */
final class UserLastUpdatedAtPolicy
{
    /**
     * @var list<string>
     */
    private const array AUTH_AND_PASSWORD_FIELDS = [
        'createdAt',
        'failedLoginAttempts',
        'lastFailedLoginAt',
        'lastLoginAt',
        'lastLoginIp',
        'lastUpdatedAt',
        'noLoginUntil',
        'password',
        'passwordUpdatedAt',
    ];

    /**
     * @param list<string> $changedFields
     */
    public function shouldTouch(array $changedFields): bool
    {
        foreach ($changedFields as $field) {
            if (!in_array($field, self::AUTH_AND_PASSWORD_FIELDS, true)) {
                return true;
            }
        }

        return false;
    }
}
