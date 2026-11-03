<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Entity\Module;

final readonly class ModuleTreeNode
{
    /**
     * @param list<ModuleTreeNode> $children
     * @param list<int>            $excludedParentIds
     */
    public function __construct(
        public Module $module,
        public int $depth,
        public bool $locked,
        public array $children,
        public array $excludedParentIds,
    ) {
    }
}
