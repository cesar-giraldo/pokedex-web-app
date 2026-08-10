<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Radio button reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * {# Varias opciones del mismo grupo comparten el atributo name #}
 * <twig:component_radio
 *     label="Default"
 *     name="demo_radio"
 *     id="demo-radio-default"
 *     value="default"
 *     :checked="false"
 * />
 * <twig:component_radio
 *     label="Secondary"
 *     name="demo_radio"
 *     id="demo-radio-secondary"
 *     value="secondary"
 *     :checked="true"
 *     :required="true"
 *     :disabled="false"
 *     error="Debes seleccionar una opción."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: texto visible junto al radio
 * - name: nombre del grupo de radios (debe repetirse en cada opción del grupo)
 * - id: identificador único del input (se autogenera si se omite)
 * - value: valor enviado cuando la opción está seleccionada
 * - checked: estado inicial seleccionado
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda
 */
#[AsTwigComponent(
    name: 'component_radio',
    template: '@admin/components/radio_component.html.twig'
)]
final class RadioComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $value = '';

    public bool $checked = false;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'radio-' . bin2hex(random_bytes(4));
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
