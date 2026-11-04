<?php

declare(strict_types=1);

namespace App\Tests\Module;

use App\Entity\Module;
use App\Module\ModuleAvailability;
use App\Repository\ModuleRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleAvailability::class)]
#[Group('unit')]
final class ModuleAvailabilityTest extends TestCase
{
    public function testEnabledModuleWithoutParentIsAvailable(): void
    {
        $module = $this->module('UI Kit', 'module_admin_ui_kit', true);

        self::assertTrue($this->availability([$module])->isEnabled('module_admin_ui_kit'));
    }

    public function testDisabledModuleIsUnavailable(): void
    {
        $module = $this->module('UI Kit', 'module_admin_ui_kit', false);

        self::assertFalse($this->availability([$module])->isEnabled('module_admin_ui_kit'));
    }

    public function testUnknownSlugIsUnavailable(): void
    {
        self::assertFalse($this->availability([])->isEnabled('module_admin_ui_kit'));
    }

    public function testEnabledChildOfDisabledParentIsUnavailable(): void
    {
        $parent = $this->module('Admin Secure Area', 'module_admin_secure_area', false);
        $child = $this->module('UI Kit', 'module_admin_ui_kit', true, $parent);

        self::assertFalse($this->availability([$parent, $child])->isEnabled('module_admin_ui_kit'));
    }

    public function testEnabledChildOfEnabledParentIsAvailable(): void
    {
        $parent = $this->module('Admin Secure Area', 'module_admin_secure_area', true);
        $child = $this->module('UI Kit', 'module_admin_ui_kit', true, $parent);

        self::assertTrue($this->availability([$parent, $child])->isEnabled('module_admin_ui_kit'));
    }

    public function testCycleIsUnavailable(): void
    {
        $parent = $this->module('Admin Secure Area', 'module_admin_secure_area', true);
        $child = $this->module('UI Kit', 'module_admin_ui_kit', true, $parent);
        $parent->setParent($child);

        self::assertFalse($this->availability([$parent, $child])->isEnabled('module_admin_ui_kit'));
    }

    public function testCachesModulesUntilReset(): void
    {
        $module = $this->module('UI Kit', 'module_admin_ui_kit', true);
        $repository = $this->createMock(ModuleRepository::class);
        $repository->expects(self::exactly(2))->method('findAllOrdered')->willReturn([$module]);
        $availability = new ModuleAvailability($repository);

        self::assertTrue($availability->isEnabled('module_admin_ui_kit'));
        self::assertTrue($availability->isEnabled('module_admin_ui_kit'));

        $availability->reset();

        self::assertTrue($availability->isEnabled('module_admin_ui_kit'));
    }

    /**
     * @param list<Module> $modules
     */
    private function availability(array $modules): ModuleAvailability
    {
        $repository = $this->createMock(ModuleRepository::class);
        $repository->expects(self::once())->method('findAllOrdered')->willReturn($modules);

        return new ModuleAvailability($repository);
    }

    private function module(string $name, string $slug, bool $enabled, ?Module $parent = null): Module
    {
        $module = new Module($name, $slug, '1.00');
        $module->setIsEnabled($enabled);
        $module->setParent($parent);

        return $module;
    }
}
