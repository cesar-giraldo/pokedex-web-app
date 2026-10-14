<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Campo file input reutilizable para formularios de la web pública.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_file_input
 *     label="Foto de perfil"
 *     name="user_profile_info[profileImage]"
 *     id="profile-avatar"
 *     accept="image/jpeg,image/png,image/webp"
 *     :required="false"
 *     :disabled="false"
 *     error="Debes seleccionar un archivo válido."
 *     help="Opcional. JPG, PNG o WebP. Tamaño máximo: 6 MB."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del input file en el POST
 * - id: identificador único del input; también enlaza el mensaje de error
 * - accept: tipos MIME o extensiones aceptadas (atributo accept del input)
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda (vacío los oculta)
 */
#[AsTwigComponent(
    name: 'web_file_input',
    template: '@web/components/file_input.html.twig',
)]
final class FileInputComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $accept = '';

    public string $error = '';

    public string $help = '';

    public bool $required = false;

    public bool $disabled = false;
}
