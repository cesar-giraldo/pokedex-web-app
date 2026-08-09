<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Pokemon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use function in_array;

/**
 * @extends ServiceEntityRepository<Pokemon>
 */
class PokemonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pokemon::class);
    }

    /**
     * Finds Pokemons using a QueryBuilder with optional search term.
     *
     * @param array{includeHidden?: bool} $searchParams
     *
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function findPokemonsQueryBuilder(
        ?string $term,
        string $sort,
        string $direction,
        array $searchParams = [],
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.type', 't');

        $includeHidden = $searchParams['includeHidden'] ?? false;
        if (!$includeHidden) {
            $qb->andWhere('p.isHidden IS NULL OR p.isHidden = false');
        }

        if ($term) {
            $qb->andWhere('p.name LIKE :term OR p.height LIKE :term OR t.name LIKE :term')
                ->setParameter('term', '%' . $term . '%');
        }

        // White list of allowed columns to prevent SQL injection
        $allowedColumns = ['p.name', 'p.height', 't.name', 'p.listOrder', 'p.weight', 'p.attack', 'p.defense', 'p.speed', 'p.healthPoints'];
        if (!in_array($sort, $allowedColumns)) {
            $sort = 'p.listOrder'; // Default to listOrder if the provided sort column is not allowed
        }

        $direction = 'ASC' === strtoupper($direction) ? 'ASC' : 'DESC';
        $qb->orderBy($sort, $direction);

        return $qb;
    }
}
