<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Checkbox reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_checkbox
 *     label="Default"
 *     name="demo_checkbox"
 *     id="demo-checkbox"
 *     value="1"
 *     :checked="false"
 *     :required="true"
 *     :disabled="false"
 *     error="Debes aceptar los términos."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: texto visible junto al checkbox
 * - name: nombre del input en el POST (se autogenera desde id si se omite)
 * - id: identificador único del input (se autogenera si se omite)
 * - value: valor enviado cuando está marcado
 * - checked: estado inicial marcado/desmarcado
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda
 */
#[AsTwigComponent(
    name: 'component_checkbox',
    template: '@admin/components/checkbox_component.html.twig'
)]
final class CheckboxComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $value = '1';

    public bool $checked = false;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'checkbox-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }
    }

    #[ExposeInTemplate('labelClasses')]
    public function getLabelClasses(): string
    {
        if ($this->disabled) {
            return 'flex cursor-not-allowed items-center text-sm font-medium text-gray-300 select-none dark:text-gray-700';
        }

        return 'flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400';
    }
}
