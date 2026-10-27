<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Pokemon;
use App\Entity\User;
use App\Entity\UserPokemonFavorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use function in_array;
use function mb_strtolower;
use function trim;

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

    public function countPublic(string $name, ?int $typeId, ?User $favoritesOf = null): int
    {
        $query = $favoritesOf instanceof User
            ? $this->favoriteQuery($name, $typeId, $favoritesOf)
            : $this->publicQuery($name, $typeId);

        return (int) $query
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Pokemon>
     */
    public function findPublicPage(string $name, ?int $typeId, int $offset, int $limit, ?User $favoritesOf = null): array
    {
        if ($favoritesOf instanceof User) {
            return $this->findFavoritePage($name, $typeId, $offset, $limit, $favoritesOf);
        }

        /** @var list<Pokemon> $pokemons */
        $pokemons = $this->publicQuery($name, $typeId)
            ->leftJoin('p.type', 't')
            ->addSelect('t')
            ->addOrderBy('CASE WHEN p.listOrder IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('p.listOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $pokemons;
    }

    /**
     * @return list<Pokemon>
     */
    private function findFavoritePage(string $name, ?int $typeId, int $offset, int $limit, User $user): array
    {
        /** @var list<Pokemon> $pokemons */
        $pokemons = $this->favoriteQuery($name, $typeId, $user)
            ->leftJoin('p.type', 't')
            ->addSelect('t')
            ->addOrderBy('fav.favoritedAt', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $pokemons;
    }

    public function findVisibleWithMedia(int $id): ?Pokemon
    {
        /** @var list<Pokemon> $pokemons */
        $pokemons = $this->createQueryBuilder('p')
            ->leftJoin('p.type', 't')
            ->addSelect('t')
            ->leftJoin('p.images', 'i')
            ->addSelect('i')
            ->andWhere('p.id = :id')
            ->andWhere('p.isHidden IS NULL OR p.isHidden = false')
            ->setParameter('id', $id)
            ->addOrderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $pokemons[0] ?? null;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function findPublicIdentities(): array
    {
        /** @var list<array{id: int|string, name: string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.id AS id', 'p.name AS name')
            ->andWhere('p.isHidden IS NULL OR p.isHidden = false')
            ->addOrderBy('CASE WHEN p.listOrder IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('p.listOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $identities = [];

        foreach ($rows as $row) {
            $identities[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
            ];
        }

        return $identities;
    }

    private function favoriteQuery(string $name, ?int $typeId, User $user): QueryBuilder
    {
        return $this->publicQuery($name, $typeId)
            ->innerJoin(
                UserPokemonFavorite::class,
                'fav',
                Join::WITH,
                'fav.pokemon = p AND fav.user = :favoriteUser',
            )
            ->setParameter('favoriteUser', $user);
    }

    private function publicQuery(string $name, ?int $typeId): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->andWhere('p.isHidden IS NULL OR p.isHidden = false');

        $name = trim($name);

        if ('' !== $name) {
            $queryBuilder
                ->andWhere('LOWER(p.name) LIKE :publicName')
                ->setParameter('publicName', '%' . mb_strtolower($name) . '%');
        }

        if (null !== $typeId && $typeId > 0) {
            $queryBuilder
                ->andWhere('p.type = :publicTypeId')
                ->setParameter('publicTypeId', $typeId);
        }

        return $queryBuilder;
    }
}
