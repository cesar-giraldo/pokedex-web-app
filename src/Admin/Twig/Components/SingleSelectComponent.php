<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent(
    name: 'component_single_select',
    template: '@admin/components/single_select_component.html.twig'
)]
final class SingleSelectComponent
{
    public string $label = '';

    public string $placeholder = 'Seleccione...';

    public string $name = '';

    public string $id = '';

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    /**
     * @var array<string, string>
     */
    public array $options = [];

    public string $selected = '';

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'single-select-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }

        $this->selected = '' !== $this->selected ? (string) $this->selected : '';
    }

    #[ExposeInTemplate('selectClasses')]
    public function getSelectClasses(): string
    {
        $base = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full appearance-none rounded-lg border bg-transparent bg-none px-4 py-2.5 pr-11 text-sm focus:ring-3 focus:outline-hidden dark:bg-gray-900';

        if ($this->disabled) {
            return $base . ' cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300 opacity-60 dark:border-gray-800 dark:bg-white/[0.03] dark:text-white/15';
        }

        if (null !== $this->error) {
            return $base . ' border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800';
        }

        return $base . ' border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 text-gray-400 dark:text-white/30';
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
