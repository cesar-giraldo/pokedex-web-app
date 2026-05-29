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
            $response = $this->httpClient->request(
                'GET',
                "https://pokeapi.co/api/v2/pokemon/$name"
            );
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
            $this->logger->error(
                'Error fetching Pokemon from PokeAPI',
                ['name' => $name, 'error' => $e->getMessage()]
            );

            return null;
        }
    }

    /**
     * Fetches a list of Pokemons with pagination support.
     *
     * @param int $limit  The number of Pokemons to fetch (default: 20)
     * @param int $offset The offset for pagination (default: 0)
     *
     * @return array<int, array{name: string, url: string}>
     */
    public function listPokemons(int $limit = 20, int $offset = 0): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                "https://pokeapi.co/api/v2/pokemon/?limit=$limit&offset=$offset"
            );
            $result = $response->toArray();

            return $result['results'] ?? [];
        } catch (Exception $e) {
            $this->logger->error(
                'Error fetching Pokemons from PokeAPI',
                ['limit' => $limit, 'offset' => $offset, 'error' => $e->getMessage()]
            );

            return [];
        }
    }

    public function getPokemonTypeByName(string $name): ?PokemonTypeDetails
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                "https://pokeapi.co/api/v2/type/$name"
            );
            $result = $response->toArray();

            return new PokemonTypeDetails(
                $result['damage_relations'] ?? [],
                $result['game_indices'] ?? [],
                $result['generation'] ?? [],
                $result['id'] ?? 0,
                $result['move_damage_class'] ?? [],
                $result['moves'] ?? [],
                $result['name'] ?? '',
                $result['names'] ?? [],
                $result['past_damage_relations'] ?? [],
                $result['pokemon'] ?? [],
                $result['sprites'] ?? []
            );
        } catch (Exception $e) {
            $this->logger->error(
                'Error fetching Pokemon type from PokeAPI',
                ['name' => $name, 'error' => $e->getMessage()]
            );

            return null;
        }
    }
}
