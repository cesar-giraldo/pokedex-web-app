<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components\Alerts;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Alerta de advertencia reutilizable para notificaciones del admin.
 *
 * Uso desde Twig:
 *
 * <twig:component_alert_warning
 *     message="Algunos cambios podrían no haberse guardado."
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
    name: 'component_alert_warning',
    template: '@admin/components/alerts/alert_warning_component.html.twig'
)]
final class AlertWarning
{
    public string $message = '';

    public int $autoHideDelay = 5000;

    public bool $dismissible = true;

    public string $id = '';

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'alert-warning-' . bin2hex(random_bytes(4));
        }

        if ($this->autoHideDelay < 0) {
            $this->autoHideDelay = 0;
        }
    }
}
