<?php

declare(strict_types=1);

namespace App\Tests\Api\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use const JSON_THROW_ON_ERROR;

final class PokemonApiControllerTest extends WebTestCase
{
    public function testListReturnsSuccessfulJsonResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/pokemones');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testUnknownApiRouteReturnsJsonNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/unknown-endpoint');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['error' => 'Not Found', 'code' => 404], $payload);
    }

    public function testUnknownWebRouteStillReturnsHtmlNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/unknown-web-page');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('text/html', (string) $client->getResponse()->headers->get('Content-Type'));
    }
}
