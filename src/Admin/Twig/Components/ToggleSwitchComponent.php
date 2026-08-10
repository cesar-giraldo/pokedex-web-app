<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

use function in_array;

/**
 * Toggle switch reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_toggle_switch
 *     label="Default"
 *     name="demo_toggle"
 *     id="demo-toggle"
 *     value="1"
 *     variant="brand"
 *     :checked="false"
 *     :required="true"
 *     :disabled="false"
 *     error="Debes activar esta opción."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: texto visible junto al switch
 * - name: nombre del input en el POST (se autogenera desde id si se omite)
 * - id: identificador único del input (se autogenera si se omite)
 * - value: valor enviado cuando está activado
 * - variant: estilo visual del track; brand (default) o dark
 * - checked: estado inicial activado/desactivado
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda
 */
#[AsTwigComponent(
    name: 'component_toggle_switch',
    template: '@admin/components/toggle_switch_component.html.twig'
)]
final class ToggleSwitchComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $value = '1';

    public string $variant = 'brand';

    public bool $checked = false;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'toggle-switch-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }

        if (!in_array($this->variant, ['brand', 'dark'], true)) {
            $this->variant = 'brand';
        }
    }

    #[ExposeInTemplate('labelClasses')]
    public function getLabelClasses(): string
    {
        if ($this->disabled) {
            return 'flex cursor-not-allowed items-center gap-3 text-sm font-medium text-gray-400 select-none';
        }

        return 'flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700 select-none dark:text-gray-400';
    }
}
