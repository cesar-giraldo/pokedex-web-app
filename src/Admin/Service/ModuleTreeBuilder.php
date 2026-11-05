<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Entity\Module;

final class ModuleTreeBuilder
{
    /**
     * @param list<Module> $modules
     *
     * @return list<ModuleTreeNode>
     */
    public function build(array $modules): array
    {
        $knownIds = [];
        foreach ($modules as $module) {
            $id = $module->getId();
            if (null !== $id) {
                $knownIds[$id] = true;
            }
        }

        /** @var array<int, list<Module>> $byParent */
        $byParent = [];
        foreach ($modules as $module) {
            $parentId = $module->getParent()?->getId();
            $key = null !== $parentId && isset($knownIds[$parentId]) ? $parentId : 0;
            $byParent[$key][] = $module;
        }

        foreach ($byParent as &$siblings) {
            usort($siblings, $this->compare(...));
        }
        unset($siblings);

        return $this->buildLevel($byParent, 0, 0);
    }

    /**
     * @param array<int, list<Module>> $byParent
     *
     * @return list<ModuleTreeNode>
     */
    private function buildLevel(array $byParent, int $parentKey, int $depth): array
    {
        $nodes = [];

        foreach ($byParent[$parentKey] ?? [] as $module) {
            $moduleId = $module->getId();
            $children = null !== $moduleId ? $this->buildLevel($byParent, $moduleId, $depth + 1) : [];
            $excludedParentIds = [];
            if (null !== $moduleId) {
                $excludedParentIds[] = $moduleId;
            }

            foreach ($children as $child) {
                foreach ($child->excludedParentIds as $excludedParentId) {
                    $excludedParentIds[] = $excludedParentId;
                }
            }

            $nodes[] = new ModuleTreeNode(
                $module,
                $depth,
                $module->hasInactiveAncestor(),
                $children,
                $excludedParentIds,
            );
        }

        return $nodes;
    }

    private function compare(Module $left, Module $right): int
    {
        $byOrder = (float) $left->getSortOrder() <=> (float) $right->getSortOrder();
        if (0 !== $byOrder) {
            return $byOrder;
        }

        return ($left->getId() ?? 0) <=> ($right->getId() ?? 0);
    }
}
