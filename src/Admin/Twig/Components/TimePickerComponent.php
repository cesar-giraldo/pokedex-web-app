<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use App\Admin\Twig\Components\Concerns\NormalizesComponentError;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Selector de hora reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_time_picker
 *     label="Time Select Input"
 *     name="demo_time"
 *     id="demo-time"
 *     placeholder="12:00 AM"
 *     value="09:30"
 *     min="08:00"
 *     max="18:00"
 *     :required="true"
 *     :disabled="false"
 *     error="Debes seleccionar una hora válida."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del input en el POST (se autogenera desde id si se omite)
 * - id: identificador único del input (se autogenera si se omite)
 * - placeholder: texto del placeholder
 * - value: valor inicial en formato H:i
 * - min, max: rango permitido en formato H:i
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda
 */
#[AsTwigComponent(
    name: 'component_time_picker',
    template: '@admin/components/time_picker_component.html.twig'
)]
final class TimePickerComponent
{
    use NormalizesComponentError;

    public string $label = '';

    public string $placeholder = '12:00 AM';

    public string $name = '';

    public string $id = '';

    public string $value = '';

    public ?string $min = null;

    public ?string $max = null;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'time-picker-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }
    }

    #[ExposeInTemplate('inputClasses')]
    public function getInputClasses(): string
    {
        $base = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full appearance-none rounded-lg border bg-transparent bg-none px-4 py-2.5 pr-11 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';

        if ($this->disabled) {
            return $base . ' cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300 opacity-60 dark:border-gray-800 dark:bg-white/[0.03] dark:text-white/15 dark:placeholder:text-white/15';
        }

        if ($this->hasError()) {
            return $base . ' border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800';
        }

        return $base . ' border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800';
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
