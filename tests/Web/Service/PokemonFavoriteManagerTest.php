<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Entity\UserPokemonFavorite;
use App\Repository\UserPokemonFavoriteRepository;
use App\Web\Service\PokemonFavoriteManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[CoversClass(PokemonFavoriteManager::class)]
final class PokemonFavoriteManagerTest extends TestCase
{
    public function testAddDoesNotWriteWhenTheFavoriteAlreadyExists(): void
    {
        $user = new User();
        $pokemon = new Pokemon();
        $favorites = $this->createMock(UserPokemonFavoriteRepository::class);
        $favorites->expects(self::once())->method('exists')->with($user, $pokemon)->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $manager = $this->manager($favorites, $entityManager);
        $manager->add($user, $pokemon);
    }

    public function testAddPersistsANewFavorite(): void
    {
        $user = new User();
        $pokemon = new Pokemon();
        $favorites = $this->createStub(UserPokemonFavoriteRepository::class);
        $favorites->method('exists')->willReturn(false);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(UserPokemonFavorite::class));
        $entityManager->expects(self::once())->method('flush');

        $manager = $this->manager($favorites, $entityManager);
        $manager->add($user, $pokemon);
    }

    public function testRemoveIgnoresAMissingFavorite(): void
    {
        $user = new User();
        $pokemon = new Pokemon();
        $favorites = $this->createMock(UserPokemonFavoriteRepository::class);
        $favorites->expects(self::once())->method('findOneFor')->with($user, $pokemon)->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $manager = $this->manager($favorites, $entityManager);
        $manager->remove($user, $pokemon);
    }

    public function testRemoveDeletesAnExistingFavorite(): void
    {
        $user = new User();
        $pokemon = new Pokemon();
        $favorite = new UserPokemonFavorite($user, $pokemon);
        $favorites = $this->createStub(UserPokemonFavoriteRepository::class);
        $favorites->method('findOneFor')->willReturn($favorite);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($favorite);
        $entityManager->expects(self::once())->method('flush');

        $manager = $this->manager($favorites, $entityManager);
        $manager->remove($user, $pokemon);
    }

    public function testGateFollowsTheAccountState(): void
    {
        $manager = $this->manager(
            $this->createStub(UserPokemonFavoriteRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $incomplete = new User()
            ->setApplicationRoles([UserRole::User])
            ->setStatus(UserStatus::UncompleteProfileInfo);
        $active = new User()
            ->setApplicationRoles([UserRole::User])
            ->setStatus(UserStatus::Active);
        $staff = new User()
            ->setApplicationRoles([UserRole::Admin])
            ->setStatus(UserStatus::Active);

        self::assertSame(PokemonFavoriteManager::GATE_LOGIN, $manager->gateFor(null));
        self::assertSame(PokemonFavoriteManager::GATE_PROFILE, $manager->gateFor($incomplete));
        self::assertSame(PokemonFavoriteManager::GATE_TOGGLE, $manager->gateFor($active));
        self::assertSame(PokemonFavoriteManager::GATE_LOGIN, $manager->gateFor($staff));
    }

    private function manager(
        UserPokemonFavoriteRepository $favorites,
        EntityManagerInterface $entityManager,
    ): PokemonFavoriteManager {
        return new PokemonFavoriteManager(
            $favorites,
            $entityManager,
            $this->createStub(ManagerRegistry::class),
        );
    }
}
