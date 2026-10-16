<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Checkbox reutilizable para formularios de la web pública.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_checkbox
 *     label="Recordarme"
 *     name="_remember_me"
 *     id="login-remember"
 *     value="on"
 *     :checked="false"
 *     :required="false"
 *     :disabled="false"
 *     error="Debes aceptar los términos."
 * />
 *
 * Props:
 * - label: texto visible junto al checkbox
 * - name: nombre del input en el POST
 * - id: identificador único del input; también enlaza el mensaje de error
 * - value: valor enviado cuando está marcado
 * - checked: estado inicial marcado o desmarcado
 * - required, disabled: estados del campo
 * - error: mensaje de validación (vacío lo oculta)
 */
#[AsTwigComponent(
    name: 'web_checkbox',
    template: '@web/components/checkbox.html.twig',
)]
final class CheckboxComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $value = 'on';

    public string $error = '';

    public bool $checked = false;

    public bool $required = false;

    public bool $disabled = false;
}
