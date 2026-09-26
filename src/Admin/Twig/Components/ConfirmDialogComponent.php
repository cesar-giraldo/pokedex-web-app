<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

use function bin2hex;
use function random_bytes;

/**
 * Diálogo de confirmación reutilizable para acciones destructivas del admin.
 *
 * Uso desde Twig:
 *
 * <twig:component_confirm_dialog
 *     id="pokemon-image-confirm-dialog"
 *     title="Eliminar imagen"
 *     message="¿Seguro que deseas eliminar esta imagen?"
 *     cancelLabel="Cancelar"
 *     confirmLabel="Eliminar"
 * />
 *
 * Props:
 * - title, message: textos del diálogo
 * - cancelLabel, confirmLabel: etiquetas de los botones
 * - id: identificador único del contenedor (se autogenera si se omite)
 */
#[AsTwigComponent(
    name: 'component_confirm_dialog',
    template: '@admin/components/confirm_dialog_component.html.twig'
)]
final class ConfirmDialogComponent
{
    public string $title = 'Confirmar';

    public string $message = '¿Deseas continuar?';

    public string $cancelLabel = 'Cancelar';

    public string $confirmLabel = 'Eliminar';

    public string $confirmTone = 'danger';

    public string $id = '';

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'confirm-dialog-' . bin2hex(random_bytes(4));
        }
    }
}
