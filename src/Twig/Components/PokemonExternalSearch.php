<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Service\PokeAPIClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent()]
final class PokemonExternalSearch
{
    use DefaultActionTrait;

    public function __construct(
        private PokeAPIClient $pokeApi
    ) {
    }

    #[LiveProp(writable: true)]
    public string $name = '';

    /**
     * @var array<int, mixed>|array{
     *   abilities: array<int, array{ability: array{name: string, url: string}, is_hidden: bool, slot: int}>,
     *   base_experience: int,
     *   cries: array{latest: string, legacy: string},
     *   forms: array<int, array{name: string, url: string}>,
     *   game_indices: array<int, array{game_index: int, version: array{name: string, url: string}}>,
     *   height: int,
     *   held_items: array,
     *   id: int,
     *   is_default: bool,
     *   location_area_encounters: string,
     *   moves: array<int, array{move: array{name: string, url: string}, version_group_details: array}>,
     *   name: string,
     *   order: int,
     *   past_abilities: array,
     *   past_stats: array,
     *   past_types: array,
     *   species: array{name: string, url: string},
     *   sprites: array,
     *   stats: array<int, array{base_stat: int, effort: int, stat: array{name: string, url: string}}>,
     *   types: array<int, array{slot: int, type: array{name: string, url: string}}>,
     *   weight: int
     * }|null
     */
    #[LiveProp(writable: true)]
    public ?array $pokemon = null;

    #[LiveAction]
    public function search(): void
    {
        $result = $this->pokeApi->getPokemonByName($this->name);
        if (!empty($result)) {
            $this->pokemon = $result;
        } else {
            $this->pokemon = null;
        }
    }
}
