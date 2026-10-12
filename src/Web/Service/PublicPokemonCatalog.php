<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Entity\PokemonType;
use App\Repository\PokemonImageRepository;
use App\Repository\PokemonRepository;
use App\Repository\PokemonTypeRepository;
use App\Web\View\PublicPokemonPage;

final class PublicPokemonCatalog
{
    public const int PER_PAGE = 24;

    public function __construct(
        private readonly PokemonRepository $pokemons,
        private readonly PokemonImageRepository $images,
        private readonly PokemonTypeRepository $types,
    ) {
    }

    /**
     * @return list<PokemonImage>
     */
    public function featuredImages(): array
    {
        return $this->images->findFeaturedForVisiblePokemon();
    }

    public function page(string $query, ?int $typeId, int $page): PublicPokemonPage
    {
        $resolvedTypeId = $this->resolveTypeId($typeId);
        $total = $this->pokemons->countPublic($query, $resolvedTypeId);
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pageCount);
        $pokemons = $this->pokemons->findPublicPage(
            $query,
            $resolvedTypeId,
            ($page - 1) * self::PER_PAGE,
            self::PER_PAGE,
        );

        $ids = [];

        foreach ($pokemons as $pokemon) {
            $id = $pokemon->getId();

            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return new PublicPokemonPage(
            $pokemons,
            $this->images->findCoversForPokemonIds($ids),
            $page,
            self::PER_PAGE,
            $total,
            trim($query),
            $resolvedTypeId,
        );
    }

    public function findVisible(int $id): ?Pokemon
    {
        return $this->pokemons->findVisibleWithMedia($id);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function visibleIdentities(): array
    {
        return $this->pokemons->findPublicIdentities();
    }

    /**
     * @return list<PokemonType>
     */
    public function types(): array
    {
        return $this->types->findAllOrderedByName();
    }

    private function resolveTypeId(?int $typeId): ?int
    {
        if (null === $typeId || $typeId <= 0) {
            return null;
        }

        $type = $this->types->find($typeId);

        if (!$type instanceof PokemonType) {
            return null;
        }

        return $type->getId();
    }
}
