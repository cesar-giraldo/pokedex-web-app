<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class HomeControllerTest extends WebTestCase
{
    public function testIndexRedirectsToTheDefaultLanguage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/(es|en|pt|fr)$#', $location);

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
    }
}
