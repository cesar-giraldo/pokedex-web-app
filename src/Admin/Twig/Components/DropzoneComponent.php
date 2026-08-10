<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Dropzone reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_dropzone
 *     label="Upload file"
 *     name="demo_dropzone"
 *     id="demo-dropzone"
 *     title="Drop File Here"
 *     description="Drag and drop your PNG, JPG, WebP, SVG images here or browse"
 *     browseLabel="Browse File"
 *     accept="image/png,image/jpeg,image/webp,image/svg+xml"
 *     :maxFileSizeKb="5120"
 *     acceptErrorMessage="Tipo de archivo no permitido."
 *     maxFileSizeErrorMessage="El archivo supera el tamaño máximo permitido."
 *     :multiple="false"
 *     :required="true"
 *     :disabled="false"
 *     error="El archivo supera el tamaño máximo permitido de 5 MB."
 *     help="Tamaño máximo: 5 MB."
 * />
 *
 * Props:
 * - label: etiqueta opcional sobre la zona de drop
 * - name: nombre del input file en el POST (se autogenera desde id si se omite)
 * - id: identificador único del componente (se autogenera si se omite)
 * - title, description, browseLabel: textos del área de drop
 * - accept: tipos MIME o extensiones aceptadas
 * - maxFileSizeKb: tamaño máximo en KB; 0 sin límite
 * - acceptErrorMessage, maxFileSizeErrorMessage: mensajes de validación cliente
 * - multiple, required, disabled: estados del campo
 * - error, help: mensajes de validación servidor y ayuda
 */
#[AsTwigComponent(
    name: 'component_dropzone',
    template: '@admin/components/dropzone_component.html.twig'
)]
final class DropzoneComponent
{
    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $title = 'Drop File Here';

    public string $description = 'Drag and drop your files here or browse';

    public string $browseLabel = 'Browse File';

    public string $accept = '';

    public int $maxFileSizeKb = 0;

    public string $acceptErrorMessage = 'Tipo de archivo no permitido.';

    public string $maxFileSizeErrorMessage = 'El archivo supera el tamaño máximo permitido.';

    public bool $multiple = false;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'dropzone-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }

        if ($this->maxFileSizeKb < 0) {
            $this->maxFileSizeKb = 0;
        }
    }

    #[ExposeInTemplate('maxFileSizeBytes')]
    public function getMaxFileSizeBytes(): int
    {
        return $this->maxFileSizeKb > 0 ? $this->maxFileSizeKb * 1024 : 0;
    }

    #[ExposeInTemplate('zoneClasses')]
    public function getZoneClasses(): string
    {
        $base = 'rounded-xl border border-dashed! p-7 transition-colors lg:p-10';

        if ($this->disabled) {
            return $base . ' cursor-not-allowed border-gray-200! bg-gray-50 opacity-60 dark:border-gray-800! dark:bg-white/[0.03]';
        }

        if (null !== $this->error) {
            return $base . ' border-error-300! bg-gray-50 dark:border-error-700! dark:bg-gray-900';
        }

        return $base . ' hover:border-brand-500! dark:hover:border-brand-500! cursor-pointer border-gray-300! bg-gray-50 dark:border-gray-700! dark:bg-gray-900';
    }

    #[ExposeInTemplate('labelClasses')]
    public function getLabelClasses(): string
    {
        if ($this->disabled) {
            return 'mb-1.5 block text-sm font-medium text-gray-300 dark:text-white/15';
        }

        return 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    }
}
