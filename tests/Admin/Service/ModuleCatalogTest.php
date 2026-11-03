<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Exception\ModuleHierarchyException;
use App\Admin\Service\ModuleCatalog;
use App\Entity\Module;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleCatalog::class)]
#[Group('unit')]
final class ModuleCatalogTest extends TestCase
{
    public function testRejectsCycleBeforePersisting(): void
    {
        $parent = new Module('Padre', 'padre', '1');
        $child = new Module('Hijo', 'hijo', '1');
        $child->setParent($parent);
        $parent->setParent($child);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $catalog = new ModuleCatalog($entityManager);

        $this->expectException(ModuleHierarchyException::class);
        $catalog->save($parent);
    }

    public function testDisablesSubtreeWhenMovedUnderInactiveAncestor(): void
    {
        $active = new Module('Activo', 'activo', '1');
        $active->setIsEnabled(true);
        $inactive = new Module('Inactivo', 'inactivo', '2');
        $child = new Module('Hijo', 'hijo', '1');
        $child->setIsEnabled(true);
        $grandchild = new Module('Nieto', 'nieto', '1');
        $grandchild->setIsEnabled(true);
        $grandchild->setParent($child);
        $child->setParent($inactive);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($child);
        $entityManager->expects(self::once())->method('flush');

        $forced = new ModuleCatalog($entityManager)->save($child);

        self::assertTrue($forced);
        self::assertFalse($child->isEnabled());
        self::assertFalse($grandchild->isEnabled());
        self::assertTrue($active->isEnabled());
        self::assertFalse($inactive->isEnabled());
    }

    public function testRejectsEnablingModuleUnderInactiveAncestor(): void
    {
        $root = new Module('Raiz', 'raiz', '1');
        $child = new Module('Hijo', 'hijo', '1');
        $child->setParent($root);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(ModuleHierarchyException::class);
        new ModuleCatalog($entityManager)->setEnabled($child, true);
    }

    public function testCopiesEnabledStateThroughTheWholeSubtree(): void
    {
        $root = new Module('Raiz', 'raiz', '1');
        $root->setIsEnabled(true);
        $child = new Module('Hijo', 'hijo', '1');
        $child->setParent($root);
        $grandchild = new Module('Nieto', 'nieto', '1');
        $grandchild->setParent($child);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('flush');

        $catalog = new ModuleCatalog($entityManager);

        $enabled = $catalog->setEnabled($child, true);
        self::assertTrue($child->isEnabled());
        self::assertTrue($grandchild->isEnabled());
        self::assertTrue($root->isEnabled());
        self::assertCount(2, $enabled);

        $disabled = $catalog->setEnabled($child, false);
        self::assertTrue($root->isEnabled());
        self::assertFalse($child->isEnabled());
        self::assertFalse($grandchild->isEnabled());
        self::assertCount(2, $disabled);

        $catalog->setEnabled($root, true);
        self::assertTrue($root->isEnabled());
        self::assertTrue($child->isEnabled());
        self::assertTrue($grandchild->isEnabled());
    }
}
