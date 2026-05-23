<?php

declare(strict_types=1);

namespace App\Service\PokeAPI;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PokeAPIClient
{
    // El HttpClient se inyecta automáticamente aquí
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    public function getPokemonByName(string $name): ?PokemonDetails
    {
        try {
            $response = $this->httpClient->request('GET', "https://pokeapi.co/api/v2/pokemon/$name");
            $result = $response->toArray();

            return new PokemonDetails(
                $result['abilities'] ?? [],
                $result['base_experience'] ?? 0,
                $result['cries'] ?? [],
                $result['forms'] ?? [],
                $result['game_indices'] ?? [],
                $result['height'] ?? 0,
                $result['held_items'] ?? [],
                $result['id'] ?? 0,
                $result['is_default'] ?? false,
                $result['location_area_encounters'] ?? '',
                $result['moves'] ?? [],
                $result['name'] ?? '',
                $result['order'] ?? 0,
                $result['past_abilities'] ?? [],
                $result['past_stats'] ?? [],
                $result['past_types'] ?? [],
                $result['species'] ?? [],
                $result['sprites'] ?? [],
                $result['stats'] ?? [],
                $result['types'] ?? [],
                $result['weight'] ?? 0
            );
        } catch (Exception $e) {
            $this->logger->error('Error fetching Pokemon from PokeAPI', ['name' => $name, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
