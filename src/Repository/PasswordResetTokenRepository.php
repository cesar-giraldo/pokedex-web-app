<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\PasswordResetChannel;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findOneBySelector(string $selector): ?PasswordResetToken
    {
        $token = $this->createQueryBuilder('token')
            ->andWhere('token.selector = :selector')
            ->setParameter('selector', $selector)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $token instanceof PasswordResetToken ? $token : null;
    }

    /**
     * @return list<PasswordResetToken>
     */
    public function findUnconsumed(User $user, PasswordResetChannel $channel): array
    {
        if (null === $user->getId()) {
            return [];
        }

        /** @var list<PasswordResetToken> $tokens */
        $tokens = $this->createQueryBuilder('token')
            ->andWhere('token.user = :user')
            ->andWhere('token.channel = :channel')
            ->andWhere('token.consumedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getResult();

        return $tokens;
    }
}
