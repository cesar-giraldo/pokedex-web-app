<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Entity\UserPokemonFavorite;
use App\Repository\UserPokemonFavoriteRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

final class PokemonFavoriteManager
{
    public const string GATE_LOGIN = 'login';

    public const string GATE_PROFILE = 'profile';

    public const string GATE_TOGGLE = 'toggle';

    public function __construct(
        private readonly UserPokemonFavoriteRepository $favorites,
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function gateFor(?UserInterface $user): string
    {
        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return self::GATE_LOGIN;
        }

        if (UserStatus::Active !== $user->getStatus()) {
            return self::GATE_PROFILE;
        }

        return self::GATE_TOGGLE;
    }

    public function add(User $user, Pokemon $pokemon): void
    {
        if ($this->favorites->exists($user, $pokemon)) {
            return;
        }

        $this->entityManager->persist(new UserPokemonFavorite($user, $pokemon));

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->reopenManager();
        }
    }

    public function remove(User $user, Pokemon $pokemon): void
    {
        $favorite = $this->favorites->findOneFor($user, $pokemon);

        if (!$favorite instanceof UserPokemonFavorite) {
            return;
        }

        $this->entityManager->remove($favorite);
        $this->entityManager->flush();
    }

    /**
     * @param list<int> $pokemonIds
     *
     * @return list<int>
     */
    public function idsFor(User $user, array $pokemonIds): array
    {
        return $this->favorites->idsForPokemonIds($user, $pokemonIds);
    }

    private function reopenManager(): void
    {
        if ($this->entityManager->isOpen()) {
            return;
        }

        $this->managerRegistry->resetManager();
    }
}
