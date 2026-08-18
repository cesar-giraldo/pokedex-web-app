<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use App\Admin\Twig\Components\Concerns\NormalizesComponentError;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

use function array_key_exists;
use function in_array;
use function sprintf;

/**
 * Campo de teléfono con selector de país reutilizable para formularios del admin.
 *
 * Uso desde Twig (standalone):
 *
 * <twig:component_phone_input
 *     label="Phone"
 *     name="demo_phone"
 *     id="demo-phone"
 *     countryName="demo_phone_country"
 *     placeholder="+1 (555) 000-0000"
 *     value=""
 *     selectedCountry="US"
 *     :countryCodes="{'US': '+1', 'GB': '+44', 'CA': '+1', 'AU': '+61'}"
 *     countrySelectorPosition="left"
 *     formatRegex="^\+\d{1,3} \(\d{3}\) \d{3}-\d{4}$"
 *     :required="true"
 *     :disabled="false"
 *     error="Introduce un número de teléfono válido."
 *     help="Texto de ayuda opcional."
 * />
 *
 * Props:
 * - label: etiqueta visible del campo
 * - name: nombre del input tel en el POST (se autogenera desde id si se omite)
 * - countryName: nombre del select de país (por defecto {name}_country)
 * - id: identificador único del input tel (se autogenera si se omite)
 * - placeholder: texto de ejemplo del formato
 * - value: valor inicial formateado
 * - selectedCountry: clave del país preseleccionado en countryCodes
 * - countryCodes: array asociativo país => prefijo (ej. '+1')
 * - countrySelectorPosition: left (default) o right
 * - formatRegex: expresión regular del formato final (+1 a +3 dígitos de país)
 * - required, disabled: estados del campo
 * - error, help: mensajes de validación y ayuda
 */
#[AsTwigComponent(
    name: 'component_phone_input',
    template: '@admin/components/phone_input_component.html.twig'
)]
final class PhoneInputComponent
{
    use NormalizesComponentError;

    public const DEFAULT_FORMAT_REGEX = '^\+\d{1,3} \(\d{3}\) \d{3}-\d{4}$';

    public string $label = '';

    public string $placeholder = '+1 (555) 000-0000';

    public string $name = '';

    public string $countryName = '';

    public string $id = '';

    public string $value = '';

    public string $selectedCountry = 'US';

    public string $countrySelectorPosition = 'left';

    public string $formatRegex = self::DEFAULT_FORMAT_REGEX;

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    /**
     * @var array<string, string>
     */
    public array $countryCodes = [
        'US' => '+1',
        'GB' => '+44',
        'CA' => '+1',
        'AU' => '+61',
    ];

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'phone-input-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }

        if ('' === $this->countryName) {
            $this->countryName = sprintf('%s_country', $this->name);
        }

        if (!in_array($this->countrySelectorPosition, ['left', 'right'], true)) {
            $this->countrySelectorPosition = 'left';
        }

        if (!array_key_exists($this->selectedCountry, $this->countryCodes)) {
            $this->selectedCountry = array_key_first($this->countryCodes) ?? 'US';
        }

        if ('' === $this->formatRegex) {
            $this->formatRegex = self::DEFAULT_FORMAT_REGEX;
        }
    }

    #[ExposeInTemplate('isCountrySelectorLeft')]
    public function isCountrySelectorLeft(): bool
    {
        return 'left' === $this->countrySelectorPosition;
    }

    #[ExposeInTemplate('inputClasses')]
    public function getInputClasses(): string
    {
        $base = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';

        $padding = $this->isCountrySelectorLeft()
            ? 'pl-[84px] pr-4'
            : 'pr-[84px] pl-3';

        if ($this->disabled) {
            return $base . ' ' . $padding . ' cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300 opacity-60 dark:border-gray-800 dark:bg-white/[0.03] dark:text-white/15 dark:placeholder:text-white/15';
        }

        if ($this->hasError()) {
            return $base . ' ' . $padding . ' border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800';
        }

        return $base . ' ' . $padding . ' border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800';
    }

    #[ExposeInTemplate('countrySelectClasses')]
    public function getCountrySelectClasses(): string
    {
        $base = 'focus:border-brand-300 focus:ring-brand-500/10 h-11 appearance-none border-0 bg-transparent bg-none py-3 pr-8 pl-3.5 text-sm leading-tight text-gray-700 focus:ring-3 focus:outline-hidden dark:text-gray-400';

        if ($this->isCountrySelectorLeft()) {
            return $base . ' rounded-l-lg border-r border-gray-200 dark:border-gray-800';
        }

        return $base . ' rounded-r-lg border-l border-gray-200 dark:border-gray-800';
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
