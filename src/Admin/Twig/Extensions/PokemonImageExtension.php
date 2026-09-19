<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use App\Entity\PokemonImage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PokemonImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pokemon_image_url', $this->resolveUrl(...)),
        ];
    }

    public function resolveUrl(?PokemonImage $image): ?string
    {
        if (!$image instanceof PokemonImage || null === $image->getId()) {
            return null;
        }

        $imagePath = $image->getImagePath();
        if ('' === $imagePath) {
            return null;
        }

        return $this->urlGenerator->generate(
            'app_pokemon_image',
            ['id' => $image->getId()],
        );
    }
}
