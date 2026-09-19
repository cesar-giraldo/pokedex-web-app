<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use function str_replace;

enum SupportedLocale: string
{
    case SpanishColombia = 'es-CO';
    case SpanishPanama = 'es-PA';
    case SpanishMexico = 'es-MX';
    case SpanishArgentina = 'es-AR';
    case SpanishVenezuela = 'es-VE';
    case SpanishChile = 'es-CL';
    case SpanishPeru = 'es-PE';
    case SpanishEcuador = 'es-EC';
    case SpanishUruguay = 'es-UY';
    case SpanishCostaRica = 'es-CR';
    case SpanishSpain = 'es-ES';
    case SpanishUnitedStates = 'es-US';
    case EnglishUnitedStates = 'en-US';
    case EnglishUnitedKingdom = 'en-GB';
    case EnglishCanada = 'en-CA';
    case PortugueseBrazil = 'pt-BR';
    case PortuguesePortugal = 'pt-PT';
    case FrenchFrance = 'fr-FR';
    case FrenchCanada = 'fr-CA';

    public function label(): string
    {
        return match ($this) {
            self::SpanishColombia => 'Español (Colombia) - es-CO',
            self::SpanishPanama => 'Español (Panamá) - es-PA',
            self::SpanishMexico => 'Español (México) - es-MX',
            self::SpanishArgentina => 'Español (Argentina) - es-AR',
            self::SpanishVenezuela => 'Español (Venezuela) - es-VE',
            self::SpanishChile => 'Español (Chile) - es-CL',
            self::SpanishPeru => 'Español (Perú) - es-PE',
            self::SpanishEcuador => 'Español (Ecuador) - es-EC',
            self::SpanishUruguay => 'Español (Uruguay) - es-UY',
            self::SpanishCostaRica => 'Español (Costa Rica) - es-CR',
            self::SpanishSpain => 'Español (España) - es-ES',
            self::SpanishUnitedStates => 'Español (Estados Unidos) - es-US',
            self::EnglishUnitedStates => 'English (United States) - en-US',
            self::EnglishUnitedKingdom => 'English (United Kingdom) - en-GB',
            self::EnglishCanada => 'English (Canada) - en-CA',
            self::PortugueseBrazil => 'Português (Brasil) - pt-BR',
            self::PortuguesePortugal => 'Português (Portugal) - pt-PT',
            self::FrenchFrance => 'Français (France) - fr-FR',
            self::FrenchCanada => 'Français (Canada) - fr-CA',
        };
    }

    /**
     * ICU / Intl locale tag (es_CO instead of es-CO).
     */
    public function intlLocale(): string
    {
        return str_replace('-', '_', $this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $locale) {
            $choices[$locale->label()] = $locale->value;
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $locale) {
            $options[$locale->value] = $locale->label();
        }

        return $options;
    }
}
