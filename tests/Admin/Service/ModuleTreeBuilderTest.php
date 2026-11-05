<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\ModuleTreeBuilder;
use App\Admin\Service\ModuleTreeNode;
use App\Entity\Module;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ModuleTreeBuilder::class)]
#[Group('unit')]
final class ModuleTreeBuilderTest extends TestCase
{
    public function testOrdersRootsAndNestsChildrenBySortOrder(): void
    {
        $later = $this->module('Documentos', 'documentos', '2', 1);
        $earlier = $this->module('Pokemones', 'pokemones', '1', 3);
        $sameOrder = $this->module('Legal', 'legal', '1', 4);
        $child = $this->module('Login', 'login', '2', 5, $earlier);
        $firstChild = $this->module('Recuperar', 'recuperar', '1', 6, $earlier);
        $earlier->setIsEnabled(false);

        $roots = new ModuleTreeBuilder()->build([$child, $later, $sameOrder, $earlier, $firstChild]);

        self::assertSame(['pokemones', 'legal', 'documentos'], array_map(
            static fn (ModuleTreeNode $node): string => $node->module->getSlug(),
            $roots,
        ));
        self::assertSame(['recuperar', 'login'], array_map(
            static fn (ModuleTreeNode $node): string => $node->module->getSlug(),
            $roots[0]->children,
        ));
        self::assertSame(0, $roots[0]->depth);
        self::assertSame(1, $roots[0]->children[0]->depth);
        self::assertFalse($roots[0]->locked);
        self::assertTrue($roots[0]->children[0]->locked);
        self::assertSame([3, 6, 5], $roots[0]->excludedParentIds);
    }

    private function module(string $name, string $slug, string $sortOrder, int $id, ?Module $parent = null): Module
    {
        $module = new Module($name, $slug, $sortOrder);
        if (null !== $parent) {
            $module->setParent($parent);
        }

        $property = new ReflectionProperty(Module::class, 'id');
        $property->setValue($module, $id);

        return $module;
    }
}
