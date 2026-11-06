<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\PokemonSpriteHosts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(PokemonSpriteHosts::class)]
#[Group('unit')]
final class PokemonSpriteHostsTest extends TestCase
{
    public function testAllowsOfficialHttpsSpriteHosts(): void
    {
        self::assertTrue(PokemonSpriteHosts::isAllowedUrl(
            'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/25.png',
        ));
        self::assertTrue(PokemonSpriteHosts::isAllowedUrl('https://pokeapi.co/media/sprites/pokemon/25.png'));
        self::assertTrue(PokemonSpriteHosts::isAllowedSrc(
            'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/25.png',
        ));
    }

    public function testRejectsHttpAndUnknownHosts(): void
    {
        self::assertFalse(PokemonSpriteHosts::isAllowedUrl('http://raw.githubusercontent.com/PokeAPI/sprites/25.png'));
        self::assertFalse(PokemonSpriteHosts::isAllowedUrl('https://example.com/pikachu.png'));
        self::assertFalse(PokemonSpriteHosts::isAllowedSrc('javascript:alert(1)'));
        self::assertFalse(PokemonSpriteHosts::isAllowedSrc('//evil.example/x.png'));
    }

    public function testAllowsLocalPublicPaths(): void
    {
        self::assertTrue(PokemonSpriteHosts::isAllowedSrc('/admin/images/pokemon/pokeball.png'));
        self::assertTrue(PokemonSpriteHosts::isAllowedSrc('images/pokemon/pokeball.png'));
    }
}
