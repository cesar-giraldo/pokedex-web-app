<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use const JSON_THROW_ON_ERROR;

final class HtmlExceptionPageTest extends WebTestCase
{
    public function testWebNotFoundReturnsCustomPageInProduction(): void
    {
        $client = static::createClient(['environment' => 'prod', 'debug' => false]);
        $client->request('GET', '/this-route-does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Página no encontrada');
        self::assertSelectorExists('a[href="/"]');
    }

    public function testAdminNotFoundReturnsCustomPageInProduction(): void
    {
        $client = static::createClient(['environment' => 'prod', 'debug' => false]);
        $client->request('GET', '/admin/this-route-does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Not Found');
        self::assertSelectorExists('a[href="/admin/pokemons"]');
    }

    public function testWebInternalServerErrorPreviewReturnsCustomPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_error/500');

        self::assertResponseStatusCodeSame(500);
        self::assertSelectorTextContains('h1', 'Error interno del servidor');
        self::assertSelectorExists('a[href="/"]');
    }

    public function testApiNotFoundReturnsJsonResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/this-endpoint-does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(404, $payload['code']);
    }
}
