<?php

declare(strict_types=1);

namespace App\Web\View;

use App\Entity\Pokemon;
use App\Entity\PokemonImage;

final readonly class PublicPokemonPage
{
    /**
     * @param list<Pokemon>            $pokemons
     * @param array<int, PokemonImage> $covers
     */
    public function __construct(
        public array $pokemons,
        public array $covers,
        public int $page,
        public int $perPage,
        public int $total,
        public string $query,
        public ?int $typeId,
    ) {
    }

    public function pageCount(): int
    {
        if ($this->total <= 0) {
            return 1;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pageCount();
    }
}
