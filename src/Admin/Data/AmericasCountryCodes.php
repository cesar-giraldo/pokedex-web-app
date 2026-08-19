<?php

declare(strict_types=1);

namespace App\Admin\Data;

use function sprintf;

/**
 * Country calling codes for the Americas (Canada through Argentina), sorted alphabetically by country name.
 */
final class AmericasCountryCodes
{
    /**
     * @return array<string, int> display label => numeric country code (without +)
     */
    public static function formChoices(): array
    {
        $choices = [];

        foreach (self::countryNames() as $name => $code) {
            $choices[sprintf('%s (+%d)', $name, $code)] = $code;
        }

        return $choices;
    }

    public static function format(int $countryCode): string
    {
        foreach (self::countryNames() as $name => $code) {
            if ($code === $countryCode) {
                return sprintf('%s (+%d)', $name, $countryCode);
            }
        }

        return sprintf('+%d', $countryCode);
    }

    /**
     * @return array<string, int> country name => numeric country code
     */
    private static function countryNames(): array
    {
        return [
            'Antigua y Barbuda' => 1268,
            'Argentina' => 54,
            'Bahamas' => 1242,
            'Barbados' => 1246,
            'Belice' => 501,
            'Bolivia' => 591,
            'Brasil' => 55,
            'Canadá' => 1,
            'Chile' => 56,
            'Colombia' => 57,
            'Costa Rica' => 506,
            'Cuba' => 53,
            'Dominica' => 1767,
            'Ecuador' => 593,
            'El Salvador' => 503,
            'Estados Unidos' => 1,
            'Granada' => 1473,
            'Guatemala' => 502,
            'Guyana' => 592,
            'Haití' => 509,
            'Honduras' => 504,
            'Jamaica' => 1876,
            'México' => 52,
            'Nicaragua' => 505,
            'Panamá' => 507,
            'Paraguay' => 595,
            'Perú' => 51,
            'República Dominicana' => 1809,
            'San Cristóbal y Nieves' => 1869,
            'San Vicente y las Granadinas' => 1784,
            'Santa Lucía' => 1758,
            'Surinam' => 597,
            'Trinidad y Tobago' => 1868,
            'Uruguay' => 598,
            'Venezuela' => 58,
        ];
    }
}
