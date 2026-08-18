<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use App\Admin\Twig\Components\Concerns\NormalizesComponentError;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Campo email reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_email_input
 *     label="Email"
 *     name="demo_email"
 *     id="demo-email"
 *     placeholder="info@gmail.com"
 *     value="info@gmail.com"
 *     :showIcon="true"
 *     :showCopyButton="false"
 *     copyLabel="Copy"
 *     copiedLabel="Copied!"
 *     invalidEmailMessage="Introduce un correo electrónico válido."
 *     :required="true"
 *     :disabled="false"
 *     error="Introduce un correo electrónico válido."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del input en el POST (se autogenera desde id si se omite)
 * - id: identificador único del input (se autogenera si se omite)
 * - placeholder, value: texto de ejemplo y valor inicial
 * - showIcon: muestra el icono de sobre a la izquierda (default true)
 * - showCopyButton: muestra botón para copiar el email al portapapeles (default false)
 * - copyLabel, copiedLabel: textos del botón copy antes y después de copiar
 * - invalidEmailMessage: mensaje de validación cliente cuando el formato no es email
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación servidor y ayuda
 */
#[AsTwigComponent(
    name: 'component_email_input',
    template: '@admin/components/email_input_component.html.twig'
)]
final class EmailInputComponent
{
    use NormalizesComponentError;

    public string $label = 'Email';

    public string $name = '';

    public string $id = '';

    public string $placeholder = 'info@gmail.com';

    public string $value = '';

    public bool $showIcon = true;

    public bool $showCopyButton = false;

    public string $copyLabel = 'Copy';

    public string $copiedLabel = 'Copied!';

    public string $invalidEmailMessage = 'Introduce un correo electrónico válido.';

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'email-input-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }
    }

    #[ExposeInTemplate('inputClasses')]
    public function getInputClasses(): string
    {
        $base = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';

        $padding = $this->getInputPaddingClass();

        if ($this->disabled) {
            return $base . ' ' . $padding . ' cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300 opacity-60 dark:border-gray-800 dark:bg-white/[0.03] dark:text-white/15 dark:placeholder:text-white/15';
        }

        if ($this->hasError()) {
            return $base . ' ' . $padding . ' border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800';
        }

        return $base . ' ' . $padding . ' border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800';
    }

    #[ExposeInTemplate('inputPaddingClass')]
    public function getInputPaddingClass(): string
    {
        $left = $this->showIcon ? 'pl-[62px]' : 'pl-4';
        $right = $this->showCopyButton ? 'pr-[90px]' : 'pr-4';

        return $left . ' ' . $right;
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
