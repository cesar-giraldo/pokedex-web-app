<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Botón submit reutilizable para formularios de la web pública.
 *
 * Al enviar el formulario se deshabilita y el texto pasa al mensaje de proceso.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_submit_button
 *     label="Guardar cambios"
 * />
 *
 * <twig:web_submit_button
 *     label="Ingresar"
 *     processingLabel="Iniciando sesión..."
 * />
 *
 * Props:
 * - label: texto visible del botón (se restaura si el envío no concluye)
 * - processingLabel: texto mientras el formulario se envía (vacío usa form.processing)
 * - name, id: atributos HTML opcionales del botón
 * - disabled: estado inicial deshabilitado
 */
#[AsTwigComponent(
    name: 'web_submit_button',
    template: '@web/components/submit_button.html.twig',
)]
final class SubmitButtonComponent
{
    public string $label = '';

    public string $processingLabel = '';

    public string $name = '';

    public string $id = '';

    public bool $disabled = false;
}
