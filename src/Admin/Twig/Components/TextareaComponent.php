<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Campo textarea reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_textarea
 *     label="Description"
 *     name="demo_description"
 *     id="demo-description"
 *     placeholder="Enter a description..."
 *     value=""
 *     :rows="6"
 *     :required="true"
 *     :disabled="false"
 *     error="Please enter a message in the textarea."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del textarea en el POST (se autogenera desde id si se omite)
 * - id: identificador único del textarea (se autogenera si se omite)
 * - placeholder: texto del placeholder
 * - value: contenido inicial (por ejemplo, tras un error de validación)
 * - rows: número de filas visibles
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda
 */
#[AsTwigComponent(
    name: 'component_textarea',
    template: '@admin/components/textarea_component.html.twig'
)]
final class TextareaComponent
{
    public string $label = '';

    public string $placeholder = 'Enter a description...';

    public string $name = '';

    public string $id = '';

    public string $value = '';

    public int $rows = 6;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'textarea-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }

        if ($this->rows < 1) {
            $this->rows = 1;
        }
    }

    #[ExposeInTemplate('textareaClasses')]
    public function getTextareaClasses(): string
    {
        $base = 'dark:bg-dark-900 shadow-theme-xs w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';

        if ($this->disabled) {
            return $base . ' cursor-not-allowed border-gray-300 focus:border-brand-300 focus:shadow-focus-ring focus:ring-0 focus:outline-hidden disabled:border-gray-100 disabled:bg-gray-50 disabled:placeholder:text-gray-300 dark:border-gray-700 dark:focus:border-brand-800 dark:disabled:border-gray-800 dark:disabled:bg-white/[0.03] dark:disabled:placeholder:text-white/15';
        }

        if (null !== $this->error) {
            return $base . ' border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 focus:ring-3 focus:outline-hidden';
        }

        return $base . ' border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 focus:ring-3 focus:outline-hidden';
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
