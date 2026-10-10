<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PokemonImage>
 */
class PokemonImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PokemonImage::class);
    }

    public function getNextSortOrder(Pokemon $pokemon): int
    {
        $max = $this->createQueryBuilder('i')
            ->select('MAX(i.sortOrder)')
            ->andWhere('i.pokemon = :pokemon')
            ->setParameter('pokemon', $pokemon)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }

    /**
     * @return list<PokemonImage>
     */
    public function findByPokemonOrdered(Pokemon $pokemon): array
    {
        /** @var list<PokemonImage> $images */
        $images = $this->createQueryBuilder('i')
            ->andWhere('i.pokemon = :pokemon')
            ->setParameter('pokemon', $pokemon)
            ->orderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $images;
    }

    /**
     * Primera imagen de cada Pokémon visible, en el orden del listado público.
     *
     * @return list<PokemonImage>
     */
    public function findFeaturedForVisiblePokemon(): array
    {
        /** @var list<PokemonImage> $images */
        $images = $this->createQueryBuilder('i')
            ->innerJoin('i.pokemon', 'p')
            ->addSelect('p')
            ->andWhere('p.isHidden IS NULL OR p.isHidden = false')
            ->addOrderBy('CASE WHEN p.listOrder IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('p.listOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->addOrderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->firstImagePerPokemon($images);
    }

    /**
     * @param list<int> $pokemonIds
     *
     * @return array<int, PokemonImage>
     */
    public function findCoversForPokemonIds(array $pokemonIds): array
    {
        if ([] === $pokemonIds) {
            return [];
        }

        /** @var list<PokemonImage> $images */
        $images = $this->createQueryBuilder('i')
            ->andWhere('i.pokemon IN (:pokemonIds)')
            ->setParameter('pokemonIds', $pokemonIds)
            ->addOrderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();

        $covers = [];

        foreach ($this->firstImagePerPokemon($images) as $image) {
            $pokemonId = $image->getPokemon()->getId();

            if (null !== $pokemonId) {
                $covers[$pokemonId] = $image;
            }
        }

        return $covers;
    }

    /**
     * @param list<PokemonImage> $images
     *
     * @return list<PokemonImage>
     */
    private function firstImagePerPokemon(array $images): array
    {
        $featured = [];

        foreach ($images as $image) {
            $pokemonId = $image->getPokemon()->getId();

            if (null === $pokemonId || isset($featured[$pokemonId])) {
                continue;
            }

            $featured[$pokemonId] = $image;
        }

        return array_values($featured);
    }
}
