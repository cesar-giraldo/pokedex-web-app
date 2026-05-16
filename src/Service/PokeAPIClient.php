<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PokeAPIClient
{
    // El HttpClient se inyecta automáticamente aquí
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    public function getPokemonByName(string $name): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://pokeapi.co/api/v2/pokemon/$name");
            return $response->toArray(); // Convierte el JSON automáticamente a array
        } catch (\Exception $e) {
            $this->logger->error('Error fetching Pokemon from PokeAPI', ['name' => $name, 'error' => $e->getMessage()]);
            return [];
        }
    }
}
