<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Module;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Module>
 */
class ModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Module::class);
    }

    /**
     * @return list<Module>
     */
    public function findAllOrdered(): array
    {
        /** @var list<Module> $modules */
        $modules = $this->createQueryBuilder('module')
            ->leftJoin('module.parent', 'parent')
            ->addSelect('parent')
            ->orderBy('module.sortOrder', 'ASC')
            ->addOrderBy('module.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $modules;
    }
}
