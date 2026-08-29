<?php

declare(strict_types=1);

namespace App\Admin\Validator;

use function mb_strtolower;
use function trim;

final class Normalizer
{
    public static function trim(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return trim($value);
    }

    public static function trimNickname(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return mb_strtolower(trim($value));
    }
}
