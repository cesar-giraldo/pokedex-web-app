<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(PokemonImage::class)]
#[Group('unit')]
final class PokemonImageTest extends TestCase
{
    public function testBlankDescriptionIsStoredAsNull(): void
    {
        $image = new PokemonImage()
            ->setImagePath('dev/public/pokemon/images/1/file.jpg')
            ->setDescription('   ');

        self::assertNull($image->getDescription());
        self::assertSame(1, $image->getSortOrder());
        self::assertMatchesRegularExpression('#^[1-9A-HJ-NP-Za-km-z]{22}$#', $image->getPublicToken());
    }

    public function testAddImageKeepsBothSidesInSync(): void
    {
        $pokemon = new Pokemon()
            ->setName('Bulbasaur')
            ->setHeight(7)
            ->setWeight(69);

        $image = new PokemonImage()
            ->setImagePath('dev/public/pokemon/images/1/file.jpg')
            ->setSortOrder(1);

        $pokemon->addImage($image);

        self::assertTrue($pokemon->getImages()->contains($image));
        self::assertSame($pokemon, $image->getPokemon());

        $pokemon->removeImage($image);
        self::assertFalse($pokemon->getImages()->contains($image));
    }

    public function testPublicTokenIsGeneratedAsBase58Uuid(): void
    {
        $first = new PokemonImage()->setImagePath('dev/public/pokemon/images/1/a.jpg');
        $second = new PokemonImage()->setImagePath('dev/public/pokemon/images/1/b.jpg');

        self::assertMatchesRegularExpression('#^[1-9A-HJ-NP-Za-km-z]{22}$#', $first->getPublicToken());
        self::assertMatchesRegularExpression('#^[1-9A-HJ-NP-Za-km-z]{22}$#', $second->getPublicToken());
        self::assertNotSame($first->getPublicToken(), $second->getPublicToken());
    }
}
