<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use App\Admin\Twig\Components\Concerns\NormalizesComponentError;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

use function preg_match;
use function preg_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Campo URL con prefijo reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_url_input
 *     label="URL"
 *     name="demo_url"
 *     id="demo-url"
 *     placeholder="www.example.com"
 *     value="http://www.example.com"
 *     prefix="http://"
 *     invalidUrlMessage="Introduce una URL válida."
 *     :required="true"
 *     :disabled="false"
 *     error="Introduce una URL válida."
 *     help="Incluye dominio y ruta opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del input hidden en el POST (se autogenera desde id si se omite)
 * - id: identificador único del input visible (se autogenera si se omite)
 * - placeholder: texto de ejemplo del dominio o ruta
 * - value: URL completa inicial (http://dominio/ruta)
 * - prefix: prefijo visual y lógico (default http://)
 * - invalidUrlMessage: mensaje de validación cliente cuando el formato no es URL
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación servidor y ayuda
 */
#[AsTwigComponent(
    name: 'component_url_input',
    template: '@admin/components/url_input_component.html.twig'
)]
final class UrlInputComponent
{
    use NormalizesComponentError;

    public string $label = 'URL';

    public string $name = '';

    public string $id = '';

    public string $placeholder = 'www.example.com';

    public string $value = '';

    public string $prefix = 'http://';

    public string $invalidUrlMessage = 'Introduce una URL válida.';

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'url-input-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }

        if ('' === $this->prefix) {
            $this->prefix = 'http://';
        }
    }

    #[ExposeInTemplate('displayValue')]
    public function getDisplayValue(): string
    {
        if ('' === $this->value) {
            return '';
        }

        if (str_starts_with(strtolower($this->value), strtolower($this->prefix))) {
            return substr($this->value, strlen($this->prefix));
        }

        if (preg_match('#^https?://#i', $this->value)) {
            return (string) preg_replace('#^https?://#i', '', $this->value);
        }

        return $this->value;
    }

    #[ExposeInTemplate('inputClasses')]
    public function getInputClasses(): string
    {
        $base = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';

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
        $length = strlen($this->prefix);

        return match (true) {
            $length <= 7 => 'pl-[90px]',
            $length <= 8 => 'pl-[94px]',
            default => 'pl-[100px]',
        };
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
