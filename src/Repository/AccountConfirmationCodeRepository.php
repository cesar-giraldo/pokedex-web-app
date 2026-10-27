<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccountConfirmationCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountConfirmationCode>
 */
class AccountConfirmationCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountConfirmationCode::class);
    }

    /**
     * @return list<AccountConfirmationCode>
     */
    public function findUnconsumed(User $user): array
    {
        if (null === $user->getId()) {
            return [];
        }

        /** @var list<AccountConfirmationCode> $codes */
        $codes = $this->createQueryBuilder('code')
            ->andWhere('code.user = :user')
            ->andWhere('code.consumedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('code.createdAt', 'DESC')
            ->addOrderBy('code.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $codes;
    }

    public function findLatest(User $user): ?AccountConfirmationCode
    {
        if (null === $user->getId()) {
            return null;
        }

        $code = $this->createQueryBuilder('code')
            ->andWhere('code.user = :user')
            ->setParameter('user', $user)
            ->orderBy('code.createdAt', 'DESC')
            ->addOrderBy('code.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $code instanceof AccountConfirmationCode ? $code : null;
    }
}
