<?php

declare(strict_types=1);

namespace App\Admin\Service\Excel;

use App\Entity\Pokemon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function is_file;
use function ltrim;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;

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
                return $this->loadFromUrl($sprite);
            }

            $localPath = $this->resolveLocalPath($sprite);
            if (is_file($localPath)) {
                return ['path' => $localPath, 'temporary' => false];
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
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 5]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'pokemon_sprite_');
            if (false === $tempFile) {
                return null;
            }

            file_put_contents($tempFile, $response->getContent());

            return ['path' => $tempFile, 'temporary' => true];
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveLocalPath(string $sprite): string
    {
        if (str_starts_with($sprite, '/')) {
            return $this->projectDir . '/public' . $sprite;
        }

        return $this->projectDir . '/public/' . ltrim($sprite, '/');
    }
}
