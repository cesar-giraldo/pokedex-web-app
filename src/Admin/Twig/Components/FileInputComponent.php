<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use App\Admin\Twig\Components\Concerns\NormalizesComponentError;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Campo file input reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_file_input
 *     label="Upload file"
 *     name="demo_file"
 *     id="demo-file"
 *     accept="image/png,image/jpeg,.pdf"
 *     :multiple="false"
 *     :required="true"
 *     :disabled="false"
 *     error="Debes seleccionar un archivo válido."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del input file en el POST (se autogenera desde id si se omite)
 * - id: identificador único del input (se autogenera si se omite)
 * - accept: tipos MIME o extensiones aceptadas (atributo accept del input)
 * - multiple: permite seleccionar varios archivos
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda
 */
#[AsTwigComponent(
    name: 'component_file_input',
    template: '@admin/components/file_input_component.html.twig'
)]
final class FileInputComponent
{
    use NormalizesComponentError;

    public string $label = 'Upload file';

    public string $name = '';

    public string $id = '';

    public string $accept = '';

    public bool $multiple = false;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'file-input-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }
    }

    #[ExposeInTemplate('inputClasses')]
    public function getInputClasses(): string
    {
        $base = 'shadow-theme-xs h-11 w-full overflow-hidden rounded-lg border bg-transparent text-sm transition-colors file:mr-5 file:border-collapse file:cursor-pointer file:rounded-l-lg file:border-0 file:border-r file:border-solid file:py-3 file:pr-3 file:pl-3.5 file:text-sm focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-400';

        if ($this->disabled) {
            return $base . ' cursor-not-allowed border-gray-100 text-gray-300 opacity-60 file:border-gray-200 file:bg-gray-50 file:text-gray-400 hover:file:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:file:border-gray-800 dark:file:bg-white/[0.03] dark:file:text-gray-500';
        }

        if ($this->hasError()) {
            return $base . ' border-error-300 text-gray-500 file:border-gray-200 file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100 focus:border-error-300 focus:file:ring-error-300 dark:border-error-700 dark:text-gray-400 dark:file:border-gray-800 dark:file:bg-white/[0.03] dark:file:text-gray-400';
        }

        return $base . ' border-gray-300 text-gray-500 file:border-gray-200 file:bg-gray-50 file:text-gray-700 placeholder:text-gray-400 hover:file:bg-gray-100 focus:border-brand-300 focus:file:ring-brand-300 dark:border-gray-700 dark:text-gray-400 dark:file:border-gray-800 dark:file:bg-white/[0.03] dark:file:text-gray-400';
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
