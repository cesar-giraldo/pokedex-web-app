<?php

declare(strict_types=1);

namespace App\Admin\Service;

use function in_array;
use function parse_url;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

final class PokemonSpriteHosts
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_HOSTS = [
        'raw.githubusercontent.com',
        'pokeapi.co',
    ];

    public static function isAllowedUrl(string $url): bool
    {
        $url = trim($url);

        if ('' === $url) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ('https' !== $scheme || '' === $host) {
            return false;
        }

        if (in_array($host, self::ALLOWED_HOSTS, true)) {
            return true;
        }

        return str_ends_with($host, '.pokeapi.co');
    }

    public static function isAllowedSrc(string $url): bool
    {
        $url = trim(str_replace('\\', '/', $url));

        if ('' === $url || str_starts_with($url, '//')) {
            return false;
        }

        if (str_starts_with($url, 'https://')) {
            return self::isAllowedUrl($url);
        }

        if (str_starts_with($url, 'http://')) {
            return false;
        }

        return !str_contains($url, ':');
    }
}
