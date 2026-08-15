<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function findOneByEmail(string $email): ?User
    {
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
}
