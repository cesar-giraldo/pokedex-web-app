<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use App\Admin\Service\PokemonSpriteHosts;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PokemonSpriteExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pokemon_sprite_is_allowed', PokemonSpriteHosts::isAllowedSrc(...)),
        ];
    }
}
