<?php

declare(strict_types=1);

namespace App\Service\PokeAPI;

/**
 * @psalm-immutable
 */
readonly class PokemonDetails
{
    /**
     * @param array<int, array{ability: array{name: string, url: string}, is_hidden: bool, slot: int}> $abilities
     * @param array{latest: string, legacy: string}                                                    $cries
     * @param array<int, array{name: string, url: string}>                                             $forms
     * @param array<int, array{game_index: int, version: array{name: string, url: string}}>            $game_indices
     * @param array<int, mixed>                                                                        $held_items
     * @param array<int, mixed>                                                                        $moves
     * @param array<int, mixed>                                                                        $past_abilities
     * @param array<int, mixed>                                                                        $past_stats
     * @param array<int, mixed>                                                                        $past_types
     * @param array{name: string, url: string}                                                         $species
     * @param array<string, mixed>                                                                     $sprites
     * @param array<int, array{base_stat: int, effort: int, stat: array{name: string, url: string}}>   $stats
     * @param array<int, array{slot: int, type: array{name: string, url: string}}>                     $types
     */
    public function __construct(
        public array $abilities,
        public int $base_experience,
        public array $cries,
        public array $forms,
        public array $game_indices,
        public int $height,
        public array $held_items,
        public int $id,
        public bool $is_default,
        public string $location_area_encounters,
        public array $moves,
        public string $name,
        public int $order,
        public array $past_abilities,
        public array $past_stats,
        public array $past_types,
        public array $species,
        public array $sprites,
        public array $stats,
        public array $types,
        public int $weight,
    ) {
    }
}
