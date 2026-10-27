<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Pokemon;
use App\Entity\User;
use App\Entity\UserPokemonFavorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPokemonFavorite>
 */
class UserPokemonFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPokemonFavorite::class);
    }

    public function findOneFor(User $user, Pokemon $pokemon): ?UserPokemonFavorite
    {
        return $this->findOneBy([
            'user' => $user,
            'pokemon' => $pokemon,
        ]);
    }

    public function exists(User $user, Pokemon $pokemon): bool
    {
        return $this->findOneFor($user, $pokemon) instanceof UserPokemonFavorite;
    }

    /**
     * @param list<int> $pokemonIds
     *
     * @return list<int>
     */
    public function idsForPokemonIds(User $user, array $pokemonIds): array
    {
        if ([] === $pokemonIds) {
            return [];
        }

        /** @var list<int|string> $ids */
        $ids = $this->createQueryBuilder('favorite')
            ->select('IDENTITY(favorite.pokemon)')
            ->andWhere('favorite.user = :user')
            ->andWhere('favorite.pokemon IN (:pokemonIds)')
            ->setParameter('user', $user)
            ->setParameter('pokemonIds', $pokemonIds)
            ->getQuery()
            ->getSingleColumnResult();

        $favoriteIds = [];

        foreach ($ids as $id) {
            $favoriteIds[] = (int) $id;
        }

        return $favoriteIds;
    }
}
