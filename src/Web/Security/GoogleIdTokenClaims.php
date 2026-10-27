<?php

declare(strict_types=1);

namespace App\Web\Security;

use function ctype_digit;
use function filter_var;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function mb_strtolower;
use function strlen;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final class GoogleIdTokenClaims
{
    private const array ISSUERS = [
        'accounts.google.com',
        'https://accounts.google.com',
    ];

    /**
     * @param array<string, mixed> $claims
     */
    public static function identity(array $claims, string $clientId, int $now): GoogleIdentity
    {
        if ('' === $clientId) {
            throw new GoogleSignInException('Google OAuth is not configured.');
        }

        $issuer = $claims['iss'] ?? null;

        if (!is_string($issuer) || !in_array($issuer, self::ISSUERS, true)) {
            throw new GoogleSignInException('The Google ID token issuer is not accepted.');
        }

        if (!self::audienceMatches($claims['aud'] ?? null, $clientId)) {
            throw new GoogleSignInException('The Google ID token audience is not accepted.');
        }

        if (self::expiration($claims['exp'] ?? null) <= $now) {
            throw new GoogleSignInException('The Google ID token is expired.');
        }

        if (true !== ($claims['email_verified'] ?? null)) {
            throw new GoogleSignInException('The Google account email is not verified.');
        }

        $sub = $claims['sub'] ?? null;

        if (!is_string($sub) || '' === $sub || strlen($sub) > 255) {
            throw new GoogleSignInException('The Google account identifier is missing.');
        }

        $email = self::email($claims['email'] ?? null);

        return new GoogleIdentity(
            sub: $sub,
            email: $email,
            givenName: self::text($claims['given_name'] ?? null),
            familyName: self::text($claims['family_name'] ?? null),
        );
    }

    private static function audienceMatches(mixed $audience, string $clientId): bool
    {
        if (is_string($audience)) {
            return $audience === $clientId;
        }

        if (!is_array($audience)) {
            return false;
        }

        foreach ($audience as $value) {
            if (is_string($value) && $value === $clientId) {
                return true;
            }
        }

        return false;
    }

    private static function expiration(mixed $expires): int
    {
        if (is_int($expires)) {
            return $expires;
        }

        if (is_string($expires) && ctype_digit($expires)) {
            return (int) $expires;
        }

        throw new GoogleSignInException('The Google ID token is expired.');
    }

    private static function email(mixed $email): string
    {
        if (!is_string($email)) {
            throw new GoogleSignInException('The Google account email is missing.');
        }

        $email = mb_strtolower(trim($email));
        $length = mb_strlen($email);

        if ($length < 5 || $length > 100 || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new GoogleSignInException('The Google account email cannot be used.');
        }

        return $email;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
