<?php

declare(strict_types=1);

namespace App\Admin\Controller\Concerns;

use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;

use function in_array;

trait AdminPaginatorTrait
{
    /**
     * @return array{entities: Pagerfanta<object>, current_limit: string, allowed_limits: list<int>}
     */
    private function getPagination(QueryBuilder $queryBuilder, Request $request): array
    {
        $adapter = new QueryAdapter($queryBuilder);
        $pagerfanta = new Pagerfanta($adapter);

        $strLimit = $request->query->get('limit', '10');
        $allowedLimits = [10, 25, 50];

        if ('all' === $strLimit) {
            $totalRecords = $pagerfanta->getNbResults();
            $pagerfanta->setMaxPerPage($totalRecords > 0 ? $totalRecords : 1);
        } else {
            $currentLimit = (int) $strLimit;
            if (!in_array($currentLimit, $allowedLimits, true)) {
                $currentLimit = 10;
            }
            $pagerfanta->setMaxPerPage($currentLimit);
        }

        $currentPage = $request->query->getInt('page', 1);
        $pagerfanta->setCurrentPage($currentPage);

        return [
            'entities' => $pagerfanta,
            'current_limit' => $strLimit,
            'allowed_limits' => $allowedLimits,
        ];
    }
}
