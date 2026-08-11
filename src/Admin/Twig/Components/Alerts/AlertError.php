<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components\Alerts;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Alerta de error reutilizable para notificaciones del admin.
 *
 * Uso desde Twig:
 *
 * <twig:component_alert_error
 *     message="No se pudo completar la operación."
 *     :autoHideDelay="5000"
 *     :dismissible="true"
 * />
 *
 * Props:
 * - message: texto de la notificación
 * - autoHideDelay: milisegundos antes de ocultarse automáticamente (0 para desactivar)
 * - dismissible: muestra el botón de cierre
 * - id: identificador único del contenedor (se autogenera si se omite)
 */
#[AsTwigComponent(
    name: 'component_alert_error',
    template: '@admin/components/alerts/alert_error_component.html.twig'
)]
final class AlertError
{
    public string $message = '';

    public int $autoHideDelay = 5000;

    public bool $dismissible = true;

    public string $id = '';

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'alert-error-' . bin2hex(random_bytes(4));
        }

        if ($this->autoHideDelay < 0) {
            $this->autoHideDelay = 0;
        }
    }
}
