<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Pokemon;
use App\Entity\PokemonType;
use App\Entity\User;
use App\Entity\UserPokemonFavorite;
use App\Repository\PokemonRepository;
use App\Repository\UserPokemonFavoriteRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function bin2hex;
use function random_bytes;
use function sprintf;

#[Group('integration')]
final class UserPokemonFavoriteRepositoryTest extends KernelTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $pokemonIds = [];

    /** @var list<int> */
    private array $typeIds = [];

    private ?EntityManagerInterface $entityManager = null;

    protected function tearDown(): void
    {
        if (static::$booted) {
            $this->entityManager = $this->openEntityManager();

            foreach ($this->pokemonIds as $pokemonId) {
                $pokemon = $this->entityManager->find(Pokemon::class, $pokemonId);

                if ($pokemon instanceof Pokemon) {
                    $this->entityManager->remove($pokemon);
                }
            }

            foreach ($this->typeIds as $typeId) {
                $type = $this->entityManager->find(PokemonType::class, $typeId);

                if ($type instanceof PokemonType) {
                    $this->entityManager->remove($type);
                }
            }

            foreach ($this->userIds as $userId) {
                $user = $this->entityManager->find(User::class, $userId);

                if ($user instanceof User) {
                    $this->entityManager->remove($user);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testFavoritesStayUniqueAndListOnlyThatUsersVisiblePokemon(): void
    {
        $this->boot();
        $suffix = bin2hex(random_bytes(3));
        $ownerNickname = 'fav-owner-' . $suffix;
        $otherNickname = 'fav-other-' . $suffix;
        $owner = $this->createUser($ownerNickname);
        $other = $this->createUser($otherNickname);
        $type = $this->createType('Favorito');
        $older = $this->createPokemon('Older Favorite', 20);
        $newer = $this->createPokemon('Newer Favorite', 10, $type);
        $hidden = $this->createPokemon('Hidden Favorite', 30, hidden: true);
        $otherPokemon = $this->createPokemon('Someone Else Favorite', 40);

        $this->favorite($owner, $older, new DateTimeImmutable('2026-01-01 10:00:00'));
        $this->favorite($owner, $newer, new DateTimeImmutable('2026-02-01 10:00:00'));
        $this->favorite($owner, $hidden, new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->favorite($other, $otherPokemon, new DateTimeImmutable('2026-04-01 10:00:00'));

        $duplicate = new UserPokemonFavorite($owner, $newer, new DateTimeImmutable('2026-05-01 10:00:00'));
        $this->entityManager()->persist($duplicate);

        try {
            $this->entityManager()->flush();
            self::fail('A second favorite for the same user and Pokémon should be rejected.');
        } catch (UniqueConstraintViolationException) {
            $this->entityManager = $this->openEntityManager();
        }

        $owner = $this->reloadUser($ownerNickname);
        $repository = new PokemonRepository($this->registry());
        $page = $repository->findPublicPage('', null, 0, 24, $owner);
        $names = array_map(static fn (Pokemon $pokemon): string => $pokemon->getName(), $page);

        self::assertSame(['Newer Favorite', 'Older Favorite'], $names);
        self::assertSame(2, $repository->countPublic('', null, $owner));
        self::assertSame(['Older Favorite'], array_map(
            static fn (Pokemon $pokemon): string => $pokemon->getName(),
            $repository->findPublicPage('older', null, 0, 24, $owner),
        ));
        self::assertSame(['Newer Favorite'], array_map(
            static fn (Pokemon $pokemon): string => $pokemon->getName(),
            $repository->findPublicPage('', $type->getId(), 0, 24, $owner),
        ));

        $favoriteIds = new UserPokemonFavoriteRepository($this->registry())
            ->idsForPokemonIds($owner, [(int) $older->getId(), (int) $hidden->getId(), (int) $otherPokemon->getId()]);

        self::assertContains($older->getId(), $favoriteIds);
        self::assertContains($hidden->getId(), $favoriteIds);
        self::assertNotContains($otherPokemon->getId(), $favoriteIds);
    }

    private function boot(): void
    {
        self::bootKernel();
        $this->entityManager = $this->openEntityManager();
    }

    private function entityManager(): EntityManagerInterface
    {
        self::assertInstanceOf(EntityManagerInterface::class, $this->entityManager);

        return $this->entityManager;
    }

    private function registry(): ManagerRegistry
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = static::getContainer()->get(ManagerRegistry::class);

        return $doctrine;
    }

    private function openEntityManager(): EntityManagerInterface
    {
        $doctrine = $this->registry();
        $manager = $doctrine->getManager();

        if (!$manager instanceof EntityManagerInterface || !$manager->isOpen()) {
            $reset = $doctrine->resetManager();
            self::assertInstanceOf(EntityManagerInterface::class, $reset);

            return $reset;
        }

        return $manager;
    }

    private function createUser(string $nickname): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User()
            ->setName('Favorite')
            ->setLastname('Tester')
            ->setNickname($nickname)
            ->setEmail(sprintf('%s@example.com', $nickname))
            ->setApplicationRoles([UserRole::User])
            ->setStatus(UserStatus::Active);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $id = $user->getId();
        self::assertNotNull($id);
        $this->userIds[] = $id;

        return $user;
    }

    private function reloadUser(string $nickname): User
    {
        $this->entityManager()->clear();
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['nickname' => $nickname]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createType(string $name): PokemonType
    {
        $type = new PokemonType()->setName($name . bin2hex(random_bytes(3)));
        $this->entityManager()->persist($type);
        $this->entityManager()->flush();
        $id = $type->getId();
        self::assertNotNull($id);
        $this->typeIds[] = $id;

        return $type;
    }

    private function createPokemon(string $name, int $listOrder, ?PokemonType $type = null, bool $hidden = false): Pokemon
    {
        $pokemon = new Pokemon()
            ->setName($name)
            ->setHeight(4)
            ->setWeight(40)
            ->setListOrder($listOrder)
            ->setIsHidden($hidden);

        if ($type instanceof PokemonType) {
            $pokemon->setType($type);
        }

        $this->entityManager()->persist($pokemon);
        $this->entityManager()->flush();
        $id = $pokemon->getId();
        self::assertNotNull($id);
        $this->pokemonIds[] = $id;

        return $pokemon;
    }

    private function favorite(User $user, Pokemon $pokemon, DateTimeImmutable $favoritedAt): void
    {
        $this->entityManager()->persist(new UserPokemonFavorite($user, $pokemon, $favoritedAt));
        $this->entityManager()->flush();
    }
}
