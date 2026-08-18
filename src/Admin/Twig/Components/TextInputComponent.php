<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use App\Admin\Twig\Components\Concerns\NormalizesComponentError;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

use function in_array;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function strtoupper;

/**
 * Campo text input reutilizable y configurable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_text_input
 *     label="Input"
 *     name="demo_text"
 *     id="demo-text"
 *     placeholder="Escribe aquí..."
 *     value=""
 *     :maxLength="120"
 *     :minLength="3"
 *     pattern="^[A-Z0-9]+$"
 *     patternFlags="i"
 *     patternErrorMessage="Solo se permiten letras y números."
 *     :showIcon="true"
 *     icon="heroicons:magnifying-glass"
 *     iconPosition="left"
 *     iconClass="w-5 h-5"
 *     :uppercase="false"
 *     :lowercase="false"
 *     autocomplete="off"
 *     inputMode="text"
 *     :spellcheck="false"
 *     :readonly="false"
 *     :onlyNumbers="false"
 *     :required="true"
 *     :disabled="false"
 *     error="Este campo es obligatorio."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label, name, id, placeholder, value: metadatos y contenido del campo
 * - maxLength, minLength: límites de caracteres (0 sin límite en maxLength)
 * - pattern, patternFlags, patternErrorMessage: validación cliente con expresión regular
 * - showIcon: muestra icono Heroicons (default false)
 * - icon: nombre del icono (heroicons:user-circle o user-circle)
 * - iconPosition: left (default) o right
 * - iconClass: clases Tailwind del icono
 * - uppercase, lowercase: transforma el valor al escribir (uppercase tiene prioridad)
 * - autocomplete, inputMode, spellcheck, readonly: atributos HTML del input
 * - onlyNumbers: restringe a enteros o decimales (negativos permitidos) con punto y hasta 2 decimales
 * - required, disabled, error, help: estados y mensajes del campo
 */
#[AsTwigComponent(
    name: 'component_text_input',
    template: '@admin/components/text_input_component.html.twig'
)]
final class TextInputComponent
{
    use NormalizesComponentError;

    private const ONLY_NUMBERS_PATTERN = '-?\d+(\.\d{1,2})?';

    private const ONLY_NUMBERS_PATTERN_ERROR = 'Introduce un número válido con hasta 2 decimales.';

    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $placeholder = '';

    public string $value = '';

    public int $maxLength = 0;

    public int $minLength = 0;

    public string $pattern = '';

    public string $patternFlags = '';

    public string $patternErrorMessage = 'El valor no cumple el formato requerido.';

    public bool $showIcon = false;

    public string $icon = 'heroicons:user-circle';

    public string $iconPosition = 'left';

    public string $iconClass = 'w-5 h-5';

    public bool $uppercase = false;

    public bool $lowercase = false;

    public string $autocomplete = '';

    public string $inputMode = '';

    public ?bool $spellcheck = null;

    public bool $readonly = false;

    public bool $onlyNumbers = false;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'text-input-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }

        if ($this->maxLength < 0) {
            $this->maxLength = 0;
        }

        if ($this->minLength < 0) {
            $this->minLength = 0;
        }

        if ($this->maxLength > 0 && $this->minLength > $this->maxLength) {
            $this->minLength = $this->maxLength;
        }

        if (!in_array($this->iconPosition, ['left', 'right'], true)) {
            $this->iconPosition = 'left';
        }

        if (!str_contains($this->icon, ':')) {
            $this->icon = 'heroicons:' . $this->icon;
        }

        if ($this->uppercase && $this->lowercase) {
            $this->lowercase = false;
        }

        if ($this->uppercase) {
            $this->value = strtoupper($this->value);
        } elseif ($this->lowercase) {
            $this->value = strtolower($this->value);
        }

        if ($this->onlyNumbers && '' === $this->inputMode) {
            $this->inputMode = 'decimal';
        }
    }

    #[ExposeInTemplate('isIconLeft')]
    public function isIconLeft(): bool
    {
        return $this->showIcon && 'left' === $this->iconPosition;
    }

    #[ExposeInTemplate('isIconRight')]
    public function isIconRight(): bool
    {
        return $this->showIcon && 'right' === $this->iconPosition;
    }

    #[ExposeInTemplate('patternForHtml')]
    public function getPatternForHtml(): string
    {
        $pattern = $this->getEffectivePattern();

        if ('' === $pattern) {
            return '';
        }

        if (str_starts_with($pattern, '^') || str_starts_with($pattern, '(')) {
            return $pattern;
        }

        return '^' . $pattern . '$';
    }

    #[ExposeInTemplate('effectivePattern')]
    public function getEffectivePattern(): string
    {
        if ($this->onlyNumbers) {
            return self::ONLY_NUMBERS_PATTERN;
        }

        return $this->pattern;
    }

    #[ExposeInTemplate('effectivePatternErrorMessage')]
    public function getEffectivePatternErrorMessage(): string
    {
        if ($this->onlyNumbers && '' === $this->pattern) {
            return self::ONLY_NUMBERS_PATTERN_ERROR;
        }

        return $this->patternErrorMessage;
    }

    #[ExposeInTemplate('hasPattern')]
    public function hasPattern(): bool
    {
        return '' !== $this->getEffectivePattern();
    }

    #[ExposeInTemplate('inputClasses')]
    public function getInputClasses(): string
    {
        $base = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';

        $padding = $this->getInputPaddingClass();
        $caseClass = $this->getCaseClass();

        if ($this->disabled) {
            return $base . ' ' . $padding . ' ' . $caseClass . ' cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300 opacity-60 dark:border-gray-800 dark:bg-white/[0.03] dark:text-white/15 dark:placeholder:text-white/15';
        }

        if ($this->hasError()) {
            return $base . ' ' . $padding . ' ' . $caseClass . ' border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800';
        }

        return $base . ' ' . $padding . ' ' . $caseClass . ' border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800';
    }

    #[ExposeInTemplate('inputPaddingClass')]
    public function getInputPaddingClass(): string
    {
        $left = $this->isIconLeft() ? 'pl-[62px]' : 'pl-4';
        $right = $this->isIconRight() ? 'pr-[62px]' : 'pr-4';

        return $left . ' ' . $right;
    }

    #[ExposeInTemplate('iconWrapperClasses')]
    public function getIconWrapperClasses(): string
    {
        $base = 'pointer-events-none absolute top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400';

        if ($this->isIconLeft()) {
            return $base . ' left-0 border-r border-gray-200 px-3.5 py-3 dark:border-gray-800';
        }

        return $base . ' right-0 border-l border-gray-200 px-3.5 py-3 dark:border-gray-800';
    }

    #[ExposeInTemplate('labelClasses')]
    public function getLabelClasses(): string
    {
        if ($this->disabled) {
            return 'mb-1.5 block text-sm font-medium text-gray-300 dark:text-white/15';
        }

        return 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    }

    private function getCaseClass(): string
    {
        if ($this->uppercase) {
            return 'uppercase';
        }

        if ($this->lowercase) {
            return 'lowercase';
        }

        return '';
    }
}
