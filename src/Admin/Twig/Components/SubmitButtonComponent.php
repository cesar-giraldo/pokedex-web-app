<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

use function in_array;

/**
 * Botón submit reutilizable para formularios de creación y edición del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_submit_button
 *     label="Guardar Cambios"
 * />
 *
 * <twig:component_submit_button
 *     label="Iniciar sesión"
 *     processingLabel="Iniciando Sesión..."
 *     layout="full"
 * />
 *
 * Props:
 * - label: texto visible del botón (se restaura si el envío no concluye)
 * - processingLabel: texto mientras el formulario se envía (default Procesando...)
 * - name, id: atributos HTML opcionales (p. ej. SubmitType de configuración general)
 * - layout: inline (páginas), responsive (modales/settings) o full (login)
 * - disabled: estado inicial deshabilitado
 */
#[AsTwigComponent(
    name: 'component_submit_button',
    template: '@admin/components/submit_button_component.html.twig'
)]
final class SubmitButtonComponent
{
    public string $label = '';

    public string $processingLabel = 'Procesando...';

    public string $name = '';

    public string $id = '';

    public string $layout = 'inline';

    public bool $disabled = false;

    public function mount(): void
    {
        if ('' === $this->processingLabel) {
            $this->processingLabel = 'Procesando...';
        }

        if (!in_array($this->layout, ['inline', 'responsive', 'full'], true)) {
            $this->layout = 'inline';
        }
    }

    #[ExposeInTemplate('buttonClasses')]
    public function getButtonClasses(): string
    {
        $base = 'items-center justify-center gap-2 rounded-lg bg-brand-500 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-brand-500';

        return match ($this->layout) {
            'full' => 'flex w-full px-4 py-3 shadow-theme-xs ' . $base,
            'responsive' => 'flex w-full px-4 py-2.5 sm:w-auto ' . $base,
            default => 'inline-flex px-5 py-3 shadow-theme-xs ' . $base,
        };
    }
}
