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
}
