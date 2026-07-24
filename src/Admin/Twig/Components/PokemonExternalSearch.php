<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use App\Admin\Service\PokeAPI\PokeAPIClient;
use App\Admin\Service\PokeAPI\PokemonDetails;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'pokemon_external_search',
    template: '@web/components/pokemon_external_search.html.twig'
)]
final class PokemonExternalSearch
{
    use DefaultActionTrait;

    public function __construct(
        private PokeAPIClient $pokeApi
    ) {
    }

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public ?PokemonDetails $pokemon = null;

    #[LiveAction]
    public function search(): void
    {
        $result = $this->pokeApi->getPokemonByName($this->name);
        if ($result) {
            $this->pokemon = $result;
        } else {
            $this->pokemon = null;
        }
    }
}
