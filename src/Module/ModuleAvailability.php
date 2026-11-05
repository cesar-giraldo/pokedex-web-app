<?php

declare(strict_types=1);

namespace App\Module;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use Symfony\Contracts\Service\ResetInterface;

use function spl_object_id;

/**
 * Indica si un módulo puede mostrarse y abrirse.
 * Exige que el propio módulo y todos sus ancestros estén activos.
 */
final class ModuleAvailability implements ResetInterface
{
    /** @var array<string, Module>|null */
    private ?array $bySlug = null;

    public function __construct(
        private readonly ModuleRepository $moduleRepository,
    ) {
    }

    public function isEnabled(string $slug): bool
    {
        $module = $this->modulesBySlug()[$slug] ?? null;

        if (!$module instanceof Module) {
            return false;
        }

        /** @var array<int|string, true> $seen */
        $seen = [];

        while ($module instanceof Module) {
            $identity = $module->getId() ?? spl_object_id($module);

            if (isset($seen[$identity])) {
                return false;
            }

            $seen[$identity] = true;

            if (!$module->isEnabled()) {
                return false;
            }

            $module = $module->getParent();
        }

        return true;
    }

    public function reset(): void
    {
        $this->bySlug = null;
    }

    /**
     * @return array<string, Module>
     */
    private function modulesBySlug(): array
    {
        if (null !== $this->bySlug) {
            return $this->bySlug;
        }

        $bySlug = [];

        foreach ($this->moduleRepository->findAllOrdered() as $module) {
            $bySlug[$module->getSlug()] = $module;
        }

        return $this->bySlug = $bySlug;
    }
}
