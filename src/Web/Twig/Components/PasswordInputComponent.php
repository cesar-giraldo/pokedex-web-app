<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Campo de contraseña reutilizable para formularios de la web pública.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_password_input
 *     label="Contraseña"
 *     name="user_profile_password[plainPassword]"
 *     id="profile-new-password"
 *     value=""
 *     placeholder=""
 *     autocomplete="new-password"
 *     showLabel="Mostrar contraseña"
 *     hideLabel="Ocultar contraseña"
 *     :maxLength="100"
 *     :required="true"
 *     :disabled="false"
 *     error="La contraseña debe tener al menos 5 caracteres e incluir letras y números."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del input en el POST
 * - id: identificador único del input; también enlaza el mensaje de error
 * - value: valor inicial
 * - placeholder: texto del placeholder
 * - autocomplete: hint del navegador; usar current-password o new-password
 * - showLabel, hideLabel: textos del botón que muestra u oculta la contraseña
 * - maxLength: límite de caracteres (0 omite el atributo)
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda (vacío los oculta)
 */
#[AsTwigComponent(
    name: 'web_password_input',
    template: '@web/components/password_input.html.twig',
)]
final class PasswordInputComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $value = '';

    public string $autocomplete = 'current-password';

    public string $placeholder = '';

    public string $error = '';

    public string $help = '';

    public string $showLabel = '';

    public string $hideLabel = '';

    public int $maxLength = 0;

    public bool $required = false;

    public bool $disabled = false;
}
