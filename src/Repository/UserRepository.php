<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use function implode;
use function in_array;
use function is_array;
use function is_string;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByNickname(string $nickname): ?User
    {
        $normalizedNickname = User::normalizeNickname($nickname);

        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.nickname) = :nickname')
            ->setParameter('nickname', $normalizedNickname)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string|array{email?: string|null} $email
     */
    public function findOneByEmail(string|array $email): ?User
    {
        if (is_array($email)) {
            $email = $email['email'] ?? null;
        }

        if (!is_string($email) || '' === trim($email)) {
            return null;
        }

        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByGoogleSub(string $googleSub): ?User
    {
        $googleSub = trim($googleSub);

        if ('' === $googleSub) {
            return null;
        }

        return $this->createQueryBuilder('u')
            ->andWhere('u.googleSub = :googleSub')
            ->setParameter('googleSub', $googleSub)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByNickname(string $nickname): bool
    {
        return $this->findOneByNickname($nickname) instanceof User;
    }

    public function existsByEmail(string $email): bool
    {
        return $this->findOneByEmail($email) instanceof User;
    }

    public function existsByNicknameOrEmail(string $nickname, string $email): bool
    {
        return $this->existsByNickname($nickname) || $this->existsByEmail($email);
    }

    /**
     * @param array{excludeHidden?: bool, roles?: list<UserRole>} $options
     */
    public function findBackendUsersQueryBuilder(
        ?string $term,
        string $sort,
        string $direction,
        array $options = [],
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('u');
        $this->restrictToRoles($qb, $options['roles'] ?? []);

        if ($options['excludeHidden'] ?? false) {
            $qb->andWhere('u.isHidden IS NULL OR u.isHidden = false');
        }

        if (null !== $term && '' !== $term) {
            $qb->andWhere(
                'u.name LIKE :term OR u.lastname LIKE :term OR u.email LIKE :term OR u.nickname LIKE :term OR u.cellphone LIKE :term',
            )->setParameter('term', '%' . $term . '%');
        }

        $allowedColumns = ['u.name', 'u.lastname', 'u.email', 'u.nickname', 'u.status', 'u.createdAt'];
        if (!in_array($sort, $allowedColumns, true)) {
            $sort = 'u.createdAt';
        }

        $direction = 'ASC' === strtoupper($direction) ? 'ASC' : 'DESC';
        $qb->orderBy($sort, $direction);

        return $qb;
    }

    /**
     * An empty selection keeps the staff directory: roles with backend access.
     *
     * @param list<UserRole> $roles
     */
    private function restrictToRoles(QueryBuilder $qb, array $roles): void
    {
        if ([] === $roles) {
            foreach (UserRole::cases() as $role) {
                if ($role->grantsBackendAccess()) {
                    $roles[] = $role;
                }
            }
        }

        $rolesAsText = 'JSON_AS_TEXT(u.roles)';
        $expressions = [];

        foreach ($roles as $index => $role) {
            $parameter = 'listedRole' . $index;
            $expressions[] = $rolesAsText . ' LIKE :' . $parameter;
            $qb->setParameter($parameter, '%"' . $role->value . '"%');
        }

        $qb->andWhere($qb->expr()->orX(...$expressions));
    }

    /**
     * @param list<UserRole> $roles
     *
     * @return list<User>
     */
    public function findByRoles(array $roles, ?UserStatus $status = null, bool $excludeHidden = false): array
    {
        if ([] === $roles) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('u');
        $rolesAsText = 'JSON_AS_TEXT(u.roles)';
        $conditions = [];

        foreach ($roles as $index => $role) {
            $parameter = 'role' . $index;
            $conditions[] = $rolesAsText . ' LIKE :' . $parameter;
            $queryBuilder->setParameter($parameter, '%"' . $role->value . '"%');
        }

        $queryBuilder->andWhere(implode(' OR ', $conditions));

        if (null !== $status) {
            $queryBuilder
                ->andWhere('u.status = :status')
                ->setParameter('status', $status);
        }

        if ($excludeHidden) {
            $queryBuilder->andWhere('u.isHidden IS NULL OR u.isHidden = false');
        }

        /** @var list<User> $users */
        $users = $queryBuilder->getQuery()->getResult();

        return $users;
    }

    /**
     * @return list<User>
     */
    public function findByStatus(UserStatus $status): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();

        return $users;
    }
}
