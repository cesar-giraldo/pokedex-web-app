<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Botón submit reutilizable para formularios de la web pública.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_submit_button
 *     label="Guardar cambios"
 * />
 *
 * Props:
 * - label: texto visible del botón
 */
#[AsTwigComponent(
    name: 'web_submit_button',
    template: '@web/components/submit_button.html.twig',
)]
final class SubmitButtonComponent
{
    public string $label = '';
}
