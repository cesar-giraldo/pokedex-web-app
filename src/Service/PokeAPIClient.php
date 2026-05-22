<?php

declare(strict_types=1);

namespace App\Service;

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

    /**
     * @return array<int, mixed>|array{
     *   abilities: array<int, array{ability: array{name: string, url: string}, is_hidden: bool, slot: int}>,
     *   base_experience: int,
     *   cries: array{latest: string, legacy: string},
     *   forms: array<int, array{name: string, url: string}>,
     *   game_indices: array<int, array{game_index: int, version: array{name: string, url: string}}>,
     *   height: int,
     *   held_items: array,
     *   id: int,
     *   is_default: bool,
     *   location_area_encounters: string,
     *   moves: array<int, array{move: array{name: string, url: string}, version_group_details: array}>,
     *   name: string,
     *   order: int,
     *   past_abilities: array,
     *   past_stats: array,
     *   past_types: array,
     *   species: array{name: string, url: string},
     *   sprites: array,
     *   stats: array<int, array{base_stat: int, effort: int, stat: array{name: string, url: string}}>,
     *   types: array<int, array{slot: int, type: array{name: string, url: string}}>,
     *   weight: int
     * }
     */
    public function getPokemonByName(string $name): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://pokeapi.co/api/v2/pokemon/$name");

            return $response->toArray(); // Convierte el JSON automáticamente a array
        } catch (Exception $e) {
            $this->logger->error('Error fetching Pokemon from PokeAPI', ['name' => $name, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
