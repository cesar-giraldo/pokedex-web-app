<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\UserRole;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

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
     * @param array{excludeDevelopers?: bool, excludeHidden?: bool} $options
     */
    public function findBackendUsersQueryBuilder(
        ?string $term,
        string $sort,
        string $direction,
        array $options = [],
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('u');

        $qb->andWhere(
            'u.roles LIKE :adminRole OR u.roles LIKE :developerRole OR u.roles LIKE :operatorRole',
        )
            ->setParameter('adminRole', '%"' . UserRole::Admin->value . '"%')
            ->setParameter('developerRole', '%"' . UserRole::Developer->value . '"%')
            ->setParameter('operatorRole', '%"' . UserRole::Operator->value . '"%');

        if ($options['excludeDevelopers'] ?? false) {
            $qb->andWhere('u.roles NOT LIKE :excludeDeveloperRole')
                ->setParameter('excludeDeveloperRole', '%"' . UserRole::Developer->value . '"%');
        }

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
}
