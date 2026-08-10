<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

use function in_array;
use function sprintf;

#[AsTwigComponent(
    name: 'component_multi_select',
    template: '@admin/components/multi_select_component.html.twig'
)]
final class MultiSelectComponent
{
    public string $label = '';

    /** @deprecated Use $label instead. Kept for backward compatibility. */
    public string $title = '';

    public string $placeholder = 'Seleccione...';

    public string $name = '';

    public string $id = '';

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public ?int $maxSelections = null;

    /**
     * @var array<string, string>
     */
    public array $options = [];

    /**
     * @var list<string|int>
     */
    public array $selected = [];

    public function mount(): void
    {
        if ('' === $this->label && '' !== $this->title) {
            $this->label = $this->title;
        }

        if ('' === $this->id) {
            $this->id = 'multi-select-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = sprintf('%s[]', $this->id);
        }

        $this->selected = array_map(strval(...), $this->selected);
    }

    public function isSelected(string|int $value): bool
    {
        return in_array((string) $value, array_map(strval(...), $this->selected), true);
    }

    #[ExposeInTemplate('triggerClasses')]
    public function getTriggerClasses(): string
    {
        $base = 'shadow-theme-xs mb-2 flex h-11 w-full rounded-lg border py-1.5 pr-3 pl-3 outline-hidden transition dark:bg-gray-900';

        if ($this->disabled) {
            return $base . ' cursor-not-allowed border-gray-100 bg-gray-50 opacity-60 dark:border-gray-800 dark:bg-white/[0.03]';
        }

        if (null !== $this->error) {
            return $base . ' border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800';
        }

        return $base . ' border-gray-300 focus:border-brand-300 focus:shadow-focus-ring dark:border-gray-700 dark:focus:border-brand-300';
    }

    #[ExposeInTemplate('labelClasses')]
    public function getLabelClasses(): string
    {
        if ($this->disabled) {
            return 'mb-1.5 block text-sm font-medium text-gray-300 dark:text-white/15';
        }

        return 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    }
}
