<?php

declare(strict_types=1);

namespace App\Tests\Api\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use const JSON_THROW_ON_ERROR;

#[Group('functional')]
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

    public function testApiResponsesIncludeCorsHeadersForAllowedOrigin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/pokemones', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('Access-Control-Allow-Origin');
        self::assertSame('http://localhost:3000', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testApiPreflightRequestReturnsCorsHeaders(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/api/v1/pokemones', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('Access-Control-Allow-Origin');
        self::assertResponseHasHeader('Access-Control-Allow-Methods');
        self::assertSame('http://localhost:3000', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testWebResponsesDoNotIncludeCorsHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
        ]);

        self::assertResponseIsSuccessful();
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }
}
