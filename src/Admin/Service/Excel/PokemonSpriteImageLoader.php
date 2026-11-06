<?php

declare(strict_types=1);

namespace App\Admin\Service\Excel;

use App\Admin\Service\PokemonSpriteHosts;
use App\Entity\Pokemon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function file_put_contents;
use function is_file;
use function ltrim;
use function realpath;
use function str_replace;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;

use const DIRECTORY_SEPARATOR;

class PokemonSpriteImageLoader
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $projectDir,
    ) {
    }

    /**
     * @return array{path: string, temporary: bool}|null
     */
    public function load(Pokemon $pokemon): ?array
    {
        $sprite = $pokemon->getSpriteFront() ?: $pokemon->getSpriteBack();

        if (null !== $sprite && '' !== $sprite) {
            if (str_starts_with($sprite, 'http://') || str_starts_with($sprite, 'https://')) {
                $remote = $this->loadFromUrl($sprite);
                if (null !== $remote) {
                    return $remote;
                }
            } else {
                $localPath = $this->resolveLocalPath($sprite);
                if (null !== $localPath && is_file($localPath)) {
                    return ['path' => $localPath, 'temporary' => false];
                }
            }
        }

        $fallback = $this->projectDir . '/public/admin/images/pokemon/pokeball.png';
        if (is_file($fallback)) {
            return ['path' => $fallback, 'temporary' => false];
        }

        return null;
    }

    /**
     * @return array{path: string, temporary: bool}|null
     */
    private function loadFromUrl(string $url): ?array
    {
        if (!PokemonSpriteHosts::isAllowedUrl($url)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 5,
                'max_redirects' => 0,
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'pokemon_sprite_');
            if (false === $tempFile) {
                return null;
            }

            file_put_contents($tempFile, $response->getContent());

            return ['path' => $tempFile, 'temporary' => true];
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveLocalPath(string $sprite): ?string
    {
        $publicDir = realpath($this->projectDir . '/public');

        if (false === $publicDir) {
            return null;
        }

        $candidate = $publicDir . '/' . ltrim(str_replace('\\', '/', $sprite), '/');
        $resolved = realpath($candidate);

        if (false === $resolved) {
            return null;
        }

        $publicPrefix = $publicDir . DIRECTORY_SEPARATOR;

        if ($resolved !== $publicDir && !str_starts_with($resolved, $publicPrefix)) {
            return null;
        }

        return $resolved;
    }
}
