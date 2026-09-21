<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Extensions;

use App\Admin\Twig\Extensions\PokemonImageExtension;
use App\Entity\PokemonImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(PokemonImageExtension::class)]
#[Group('unit')]
final class PokemonImageExtensionTest extends TestCase
{
    public function testRegistersTwigFunction(): void
    {
        $extension = new PokemonImageExtension($this->createMock(UrlGeneratorInterface::class));
        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('pokemon_image_url', $functions[0]->getName());
    }

    public function testResolveUrlReturnsNullWithoutPersistedImage(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $extension = new PokemonImageExtension($urlGenerator);

        self::assertNull($extension->resolveUrl(null));
        self::assertNull($extension->resolveUrl(new PokemonImage()->setImagePath('path.jpg')));
    }

    public function testResolveUrlGeneratesThumbPathByDefault(): void
    {
        $image = $this->persistedImage();

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_pokemon_image', [
                'publicToken' => $image->getPublicToken(),
                'variant' => 'thumb',
            ])
            ->willReturn('/media/pokemon-images/' . $image->getPublicToken() . '/thumb');

        $extension = new PokemonImageExtension($urlGenerator);

        self::assertSame(
            '/media/pokemon-images/' . $image->getPublicToken() . '/thumb',
            $extension->resolveUrl($image),
        );
    }

    public function testResolveUrlOmitsVariantForOriginal(): void
    {
        $image = $this->persistedImage();

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_pokemon_image', ['publicToken' => $image->getPublicToken()])
            ->willReturn('/media/pokemon-images/' . $image->getPublicToken());

        $extension = new PokemonImageExtension($urlGenerator);

        self::assertSame(
            '/media/pokemon-images/' . $image->getPublicToken(),
            $extension->resolveUrl($image, 'original'),
        );
    }

    public function testResolveUrlReturnsNullForDisallowedVariant(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $extension = new PokemonImageExtension($urlGenerator);

        self::assertNull($extension->resolveUrl($this->persistedImage(), 'avatar'));
        self::assertNull($extension->resolveUrl($this->persistedImage(), 'unknown'));
    }

    private function persistedImage(): PokemonImage
    {
        $image = new PokemonImage()->setImagePath('dev/public/pokemon/images/1/file.jpg');
        $reflection = new ReflectionProperty(PokemonImage::class, 'id');
        $reflection->setValue($image, 15);

        return $image;
    }
}
