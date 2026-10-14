<?php

declare(strict_types=1);

namespace App\Web\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Aviso reutilizable para formularios y páginas de la web pública.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:web_alert
 *     variant="error"
 *     message="La contraseña actual no es correcta."
 * />
 *
 * <twig:web_alert
 *     variant="success"
 *     message="Tu contraseña se actualizó correctamente."
 * />
 *
 * Props:
 * - message: texto del aviso (vacío oculta el aviso)
 * - variant: error, success o status
 */
#[AsTwigComponent(
    name: 'web_alert',
    template: '@web/components/alert.html.twig',
)]
final class AlertComponent
{
    public string $message = '';

    public string $variant = 'status';
}
