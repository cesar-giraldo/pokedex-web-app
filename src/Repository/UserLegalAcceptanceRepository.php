<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserLegalAcceptance>
 */
class UserLegalAcceptanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLegalAcceptance::class);
    }

    public function hasAcceptance(User $user, LegalDocumentVersion $version): bool
    {
        $count = (int) $this->createQueryBuilder('acceptance')
            ->select('COUNT(acceptance.id)')
            ->andWhere('acceptance.user = :user')
            ->andWhere('acceptance.version = :version')
            ->setParameter('user', $user)
            ->setParameter('version', $version)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function queryForVersion(LegalDocumentVersion $version): QueryBuilder
    {
        return $this->createQueryBuilder('acceptance')
            ->addSelect('acceptedUser')
            ->innerJoin('acceptance.user', 'acceptedUser')
            ->andWhere('acceptance.version = :version')
            ->setParameter('version', $version)
            ->orderBy('acceptance.acceptedAt', 'DESC')
            ->addOrderBy('acceptance.id', 'DESC');
    }
}
