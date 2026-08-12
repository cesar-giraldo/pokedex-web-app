<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Excel;

use App\Admin\Service\Excel\PokemonSpriteImageLoader;
use App\Entity\Pokemon;
use App\Entity\PokemonType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PokemonSpriteImageLoaderTest extends TestCase
{
    public function testLoadUsesFallbackWhenPokemonHasNoSprite(): void
    {
        $loader = new PokemonSpriteImageLoader(
            new MockHttpClient(),
            dirname(__DIR__, 4),
        );

        $result = $loader->load($this->createPokemon(null, null));

        self::assertNotNull($result);
        self::assertFalse($result['temporary']);
        self::assertStringEndsWith('/public/admin/images/pokemon/pokeball.png', $result['path']);
    }

    public function testLoadDownloadsRemoteSprite(): void
    {
        $pngBytes = (string) file_get_contents(dirname(__DIR__, 4) . '/public/admin/images/pokemon/pokeball.png');
        $httpClient = new MockHttpClient([
            new MockResponse($pngBytes, ['http_code' => 200]),
        ]);

        $loader = new PokemonSpriteImageLoader($httpClient, dirname(__DIR__, 4));
        $pokemon = $this->createPokemon('https://example.com/pikachu.png', null);

        $result = $loader->load($pokemon);

        self::assertNotNull($result);
        self::assertTrue($result['temporary']);
        self::assertFileExists($result['path']);

        unlink($result['path']);
    }

    public function testLoadResolvesLocalPublicPath(): void
    {
        $loader = new PokemonSpriteImageLoader(
            new MockHttpClient(),
            dirname(__DIR__, 4),
        );

        $pokemon = $this->createPokemon('/admin/images/pokemon/pokeball.png', null);
        $result = $loader->load($pokemon);

        self::assertNotNull($result);
        self::assertFalse($result['temporary']);
        self::assertStringEndsWith('/public/admin/images/pokemon/pokeball.png', $result['path']);
    }

    /**
     * @return Pokemon
     */
    private function createPokemon(?string $spriteFront, ?string $spriteBack): Pokemon
    {
        $pokemonType = new PokemonType();
        $pokemonType->setName('Electric');

        $pokemon = new Pokemon();
        $pokemon->setName('Pikachu');
        $pokemon->setHeight(4);
        $pokemon->setWeight(60);
        $pokemon->setType($pokemonType);
        $pokemon->setSpeed(90);
        $pokemon->setAttack(55);
        $pokemon->setDefense(40);
        $pokemon->setHealthPoints(35);
        $pokemon->setIsHidden(false);
        $pokemon->setSpriteFront($spriteFront);
        $pokemon->setSpriteBack($spriteBack);

        return $pokemon;
    }
}
