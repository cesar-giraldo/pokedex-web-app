<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Admin\Exception\ModuleHierarchyException;
use App\Entity\Module;
use Doctrine\ORM\EntityManagerInterface;

use function spl_object_id;

/**
 * Reglas de jerarquía y activación del catálogo de módulos.
 */
final class ModuleCatalog
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Persiste el módulo. Si algún ancestro está inactivo, apaga el subárbol.
     *
     * @return bool true cuando la jerarquía obligó a desactivar el módulo o a sus descendientes
     */
    public function save(Module $module): bool
    {
        $this->assertAssignableParent($module);
        $forcedDisabled = false;

        if ($module->hasInactiveAncestor()) {
            foreach ($module->subtree() as $node) {
                if ($node->isEnabled()) {
                    $forcedDisabled = true;
                }

                $node->setIsEnabled(false);
            }
        }

        $this->entityManager->persist($module);
        $this->entityManager->flush();

        return $forcedDisabled;
    }

    /**
     * Copia el estado a todo el subárbol y lo guarda.
     * Encender un nodo exige que todos sus ancestros estén activos.
     *
     * @return list<Module>
     */
    public function setEnabled(Module $module, bool $enabled): array
    {
        if ($enabled && $module->hasInactiveAncestor()) {
            throw new ModuleHierarchyException('No se puede activar un módulo mientras su módulo padre está inactivo.');
        }

        $affected = $module->subtree();
        foreach ($affected as $node) {
            $node->setIsEnabled($enabled);
        }

        $this->entityManager->flush();

        return $affected;
    }

    public function assertAssignableParent(Module $module): void
    {
        $cursor = $module->getParent();
        $seen = [];

        while (null !== $cursor) {
            $objectId = spl_object_id($cursor);
            if (isset($seen[$objectId]) || $cursor === $module || $this->sameModule($cursor, $module)) {
                throw new ModuleHierarchyException('Un módulo no puede depender de sí mismo ni de uno de sus submódulos.');
            }

            $seen[$objectId] = true;
            $cursor = $cursor->getParent();
        }
    }

    private function sameModule(Module $left, Module $right): bool
    {
        $leftId = $left->getId();
        $rightId = $right->getId();

        return null !== $leftId && $leftId === $rightId;
    }
}
