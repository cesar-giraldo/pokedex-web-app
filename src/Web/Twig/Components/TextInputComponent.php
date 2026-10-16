<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Campo de texto reutilizable para formularios de la web pública.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_text_input
 *     label="Nombre"
 *     name="user_profile_info[name]"
 *     id="profile-name"
 *     type="text"
 *     value=""
 *     placeholder="Tu nombre"
 *     autocomplete="given-name"
 *     :maxLength="50"
 *     :minLength="0"
 *     :required="true"
 *     :disabled="false"
 *     error="Este campo es obligatorio."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del input en el POST
 * - id: identificador único del input; también enlaza el mensaje de error
 * - type: tipo HTML del input (text, email, etc.)
 * - value: valor inicial
 * - placeholder: texto del placeholder
 * - autocomplete: hint del navegador
 * - maxLength, minLength: límites de caracteres (0 omite el atributo)
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda (vacío los oculta)
 */
#[AsTwigComponent(
    name: 'web_text_input',
    template: '@web/components/text_input.html.twig',
)]
final class TextInputComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $type = 'text';

    public string $value = '';

    public string $autocomplete = 'off';

    public string $placeholder = '';

    public string $error = '';

    public string $help = '';

    public int $maxLength = 0;

    public int $minLength = 0;

    public bool $required = false;

    public bool $disabled = false;
}
