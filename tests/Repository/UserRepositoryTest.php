<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

#[Group('integration')]
final class UserRepositoryTest extends KernelTestCase
{
    /** @var list<int> */
    private array $createdUserIds = [];

    private ?EntityManagerInterface $entityManager = null;

    protected function tearDown(): void
    {
        if (null !== $this->entityManager) {
            foreach ($this->createdUserIds as $userId) {
                $user = $this->entityManager->find(User::class, $userId);
                if ($user instanceof User) {
                    $this->entityManager->remove($user);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testFindOneByEmailAcceptsStringCriteria(): void
    {
        $repository = $this->getRepository();
        $user = $this->createUser('email-string-lookup', UserRole::Admin, email: 'lookup@Example.com');

        $found = $repository->findOneByEmail('lookup@example.com');

        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testFindOneByEmailAcceptsUniqueEntityCriteriaArray(): void
    {
        $repository = $this->getRepository();
        $user = $this->createUser('email-array-lookup', UserRole::Admin, email: 'array-lookup@Example.com');

        $found = $repository->findOneByEmail(['email' => 'array-lookup@example.com']);

        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testFindBackendUsersQueryBuilderReturnsOnlyBackendUsers(): void
    {
        $repository = $this->getRepository();
        $developer = $this->createUser('repo-dev', UserRole::Developer);
        $admin = $this->createUser('repo-admin', UserRole::Admin);
        $client = $this->createUser('repo-client', UserRole::User);

        $results = $repository
            ->findBackendUsersQueryBuilder(null, 'u.createdAt', 'desc')
            ->getQuery()
            ->getResult();

        $nicknames = array_map(static fn (User $user): string => $user->getNickname(), $results);

        self::assertContains($developer->getNickname(), $nicknames);
        self::assertContains($admin->getNickname(), $nicknames);
        self::assertNotContains($client->getNickname(), $nicknames);
    }

    public function testFindBackendUsersQueryBuilderFiltersBySearchTerm(): void
    {
        $repository = $this->getRepository();
        $this->createUser('searchable-admin', UserRole::Admin, 'Searchable', 'Admin', 'searchable-admin@example.com');

        $results = $repository
            ->findBackendUsersQueryBuilder('searchable-admin@example.com', 'u.createdAt', 'desc')
            ->getQuery()
            ->getResult();

        self::assertCount(1, $results);
        self::assertSame('searchable-admin', $results[0]->getNickname());
    }

    public function testFindBackendUsersQueryBuilderUsesAllowedSortColumn(): void
    {
        $repository = $this->getRepository();

        $dql = $repository
            ->findBackendUsersQueryBuilder(null, 'invalid.column', 'desc')
            ->getDQL();

        self::assertStringContainsString('ORDER BY u.createdAt DESC', $dql);
    }

    public function testFindBackendUsersQueryBuilderExcludesHiddenUsersForNonDeveloperScope(): void
    {
        $repository = $this->getRepository();
        $visibleAdmin = $this->createUser('repo-visible-admin', UserRole::Admin, isHidden: false);
        $hiddenAdmin = $this->createUser('repo-hidden-admin', UserRole::Admin, isHidden: true);

        $results = $repository
            ->findBackendUsersQueryBuilder(null, 'u.createdAt', 'desc', ['excludeHidden' => true])
            ->getQuery()
            ->getResult();

        $nicknames = array_map(static fn (User $user): string => $user->getNickname(), $results);

        self::assertContains($visibleAdmin->getNickname(), $nicknames);
        self::assertNotContains($hiddenAdmin->getNickname(), $nicknames);
    }

    public function testFindBackendUsersQueryBuilderIncludesHiddenUsersForDeveloperScope(): void
    {
        $repository = $this->getRepository();
        $hiddenAdmin = $this->createUser('repo-dev-hidden-admin', UserRole::Admin, isHidden: true);

        $results = $repository
            ->findBackendUsersQueryBuilder(null, 'u.createdAt', 'desc', ['excludeHidden' => false])
            ->getQuery()
            ->getResult();

        $nicknames = array_map(static fn (User $user): string => $user->getNickname(), $results);

        self::assertContains($hiddenAdmin->getNickname(), $nicknames);
    }

    private function getRepository(): UserRepository
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var UserRepository $repository */
        $repository = $container->get(UserRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        return $repository;
    }

    private function createUser(
        string $nickname,
        UserRole $role,
        string $name = 'Test',
        string $lastname = 'User',
        string $email = '',
        bool $isHidden = true,
    ): User {
        self::assertNotNull($this->entityManager);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setName($name)
            ->setLastname($lastname)
            ->setEmail('' !== $email ? $email : sprintf('%s@example.com', $nickname))
            ->setNickname($nickname)
            ->setApplicationRoles([$role])
            ->setStatus(UserStatus::Active)
            ->setIsHidden($isHidden);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $userId = $user->getId();
        self::assertNotNull($userId);
        $this->createdUserIds[] = $userId;

        return $user;
    }
}
