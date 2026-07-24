<?php

declare(strict_types=1);

namespace App\Tests\Service\PokeAPI;

use App\Admin\Service\PokeAPI\PokeAPIClient;
use App\Admin\Service\PokeAPI\PokemonDetails;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PokeAPIClientTest extends TestCase
{
    public function testGetPokemonByNameReturnsPokemonDetailsOnSuccess(): void
    {
        $mockResponseData = [
            'abilities' => [],
            'base_experience' => 64,
            'cries' => ['latest' => 'url1', 'legacy' => 'url2'],
            'forms' => [],
            'game_indices' => [],
            'height' => 7,
            'held_items' => [],
            'id' => 25,
            'is_default' => true,
            'location_area_encounters' => 'some-url',
            'moves' => [],
            'name' => 'pikachu',
            'order' => 35,
            'past_abilities' => [],
            'past_stats' => [],
            'past_types' => [],
            'species' => ['name' => 'pikachu', 'url' => 'species-url'],
            'sprites' => [],
            'stats' => [],
            'types' => [],
            'weight' => 60,
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('pikachu'))
            ->willReturn($response);

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($mockResponseData);

        $client = new PokeAPIClient($httpClient, $logger);
        $result = $client->getPokemonByName('pikachu');

        $this->assertInstanceOf(PokemonDetails::class, $result);
        $this->assertSame('pikachu', $result->name);
        $this->assertSame(25, $result->id);
        $this->assertSame(7, $result->height);
    }

    public function testGetPokemonByNameReturnsNullOnFailure(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new Exception('API error'));

        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error fetching Pokemon from PokeAPI'),
                $this->arrayHasKey('name')
            );

        $client = new PokeAPIClient($httpClient, $logger);
        $result = $client->getPokemonByName('missingno');

        $this->assertNull($result);
    }
}
