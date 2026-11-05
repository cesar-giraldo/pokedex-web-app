<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Module;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Module::class)]
#[Group('unit')]
final class ModuleTest extends TestCase
{
    public function testStartsDisabledWithoutParentOrDescription(): void
    {
        $module = new Module('Pokemones', 'pokemones', '1');

        self::assertNull($module->getId());
        self::assertSame('Pokemones', $module->getName());
        self::assertSame('pokemones', $module->getSlug());
        self::assertSame('1.00', $module->getSortOrder());
        self::assertFalse($module->isEnabled());
        self::assertNull($module->getParent());
        self::assertNull($module->getDescription());
        self::assertInstanceOf(DateTime::class, $module->getCreatedAt());
        self::assertFalse($module->hasInactiveAncestor());
    }

    public function testKeepsParentAndChildInSync(): void
    {
        $parent = new Module('Web', 'web', '1');
        $child = new Module('Login', 'login', '2');

        $child->setParent($parent);

        self::assertSame($parent, $child->getParent());
        self::assertTrue($parent->getChildren()->contains($child));

        $child->setParent(null);

        self::assertNull($child->getParent());
        self::assertFalse($parent->getChildren()->contains($child));
    }

    public function testNormalizesSortOrderAndClearsBlankDescription(): void
    {
        $module = new Module(' Legal ', ' legal ', '2.5');
        $module->setDescription('  Texto  ');

        self::assertSame('Legal', $module->getName());
        self::assertSame('legal', $module->getSlug());
        self::assertSame('2.50', $module->getSortOrder());
        self::assertSame('Texto', $module->getDescription());

        $module->setDescription('   ');

        self::assertNull($module->getDescription());
        self::assertNull(Module::tryNormalizeSortOrder('-1'));
        self::assertNull(Module::tryNormalizeSortOrder('1.234'));
        self::assertSame('10.50', Module::tryNormalizeSortOrder('10.5'));
    }

    public function testDetectsInactiveAncestorAndCollectsSubtree(): void
    {
        $root = new Module('Raiz', 'raiz', '1');
        $child = new Module('Hijo', 'hijo', '1');
        $grandchild = new Module('Nieto', 'nieto', '1');
        $child->setParent($root);
        $grandchild->setParent($child);

        self::assertTrue($child->hasInactiveAncestor());
        self::assertSame([$child, $grandchild], $child->subtree());

        $root->setIsEnabled(true);
        $child->setIsEnabled(true);

        self::assertFalse($grandchild->hasInactiveAncestor());
    }
}
