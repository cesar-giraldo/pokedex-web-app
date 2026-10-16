<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Interruptor reutilizable para formularios de la web pública.
 *
 * El control se pinta en morado de la marca cuando está activado.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_toggle
 *     label="Eliminar imagen actual"
 *     name="user_profile_info[removeProfileImage]"
 *     id="profile-remove-avatar"
 *     value="1"
 *     :checked="false"
 *     :required="false"
 *     :disabled="false"
 *     error="Debes activar esta opción."
 *     help="Al activarlo se quitará la imagen de perfil al guardar."
 * />
 *
 * Props:
 * - label: texto visible junto al interruptor
 * - name: nombre del input en el POST
 * - id: identificador único del input; también enlaza el mensaje de error
 * - value: valor enviado cuando está activado
 * - checked: estado inicial activado o desactivado
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda (vacío los oculta)
 */
#[AsTwigComponent(
    name: 'web_toggle',
    template: '@web/components/toggle.html.twig',
)]
final class ToggleComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $value = '1';

    public string $error = '';

    public string $help = '';

    public bool $checked = false;

    public bool $required = false;

    public bool $disabled = false;
}
