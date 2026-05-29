<?php

declare(strict_types=1);

namespace App\Service\PokeAPI;

/**
 * @psalm-immutable
 */
readonly class PokemonTypeDetails
{
    /**
     * @param array{
     *   double_damage_from: array<int, array{name: string, url: string}>,
     *   double_damage_to: array<int, array{name: string, url: string}>,
     *   half_damage_from: array<int, array{name: string, url: string}>,
     *   half_damage_to: array<int, array{name: string, url: string}>,
     *   no_damage_from: array<int, array{name: string, url: string}>,
     *   no_damage_to: array<int, array{name: string, url: string}>
     * } $damage_relations
     * @param array<int, array{
     *   game_index: int,
     *   generation: array{name: string, url: string}
     * }> $game_indices
     * @param array{name: string, url: string}             $generation
     * @param array{name: string, url: string}             $move_damage_class
     * @param array<int, array{name: string, url: string}> $moves
     * @param array<int, array{
     *   language: array{name: string, url: string},
     *   name: string
     * }> $names
     * @param array<int, array{
     *   pokemon: array{name: string, url: string},
     *   slot: int
     * }> $pokemon
     * @param array<int, array<string, mixed>>                                                 $past_damage_relations
     * @param array<string, array<string, array{name_icon: string, symbol_icon: string|null}>> $sprites
     */
    public function __construct(
        public array $damage_relations,
        public array $game_indices,
        public array $generation,
        public int $id,
        public array $move_damage_class,
        public array $moves,
        public string $name,
        public array $names,
        public array $past_damage_relations,
        public array $pokemon,
        public array $sprites,
    ) {
    }
}
