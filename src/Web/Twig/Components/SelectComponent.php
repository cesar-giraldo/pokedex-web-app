<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Selector reutilizable para formularios de la web pública.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_select
 *     label="Zona horaria"
 *     name="user_profile_info[timezone]"
 *     id="profile-timezone"
 *     placeholder="Selecciona una zona horaria"
 *     :options="{'America/Bogota': 'América/Bogotá', 'Europe/Madrid': 'Europa/Madrid'}"
 *     selected="America/Bogota"
 *     :required="true"
 *     :disabled="false"
 *     error="Debes seleccionar una zona horaria."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del select en el POST
 * - id: identificador único del select; también enlaza el mensaje de error
 * - placeholder: texto de la opción vacía inicial
 * - options: array asociativo value => label
 * - selected: valor preseleccionado
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda (vacío los oculta)
 */
#[AsTwigComponent(
    name: 'web_select',
    template: '@web/components/select.html.twig',
)]
final class SelectComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $placeholder = '';

    public string $selected = '';

    public string $error = '';

    public string $help = '';

    public bool $required = false;

    public bool $disabled = false;

    /**
     * @var array<string, string>
     */
    public array $options = [];
}
