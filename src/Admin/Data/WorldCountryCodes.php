<?php

declare(strict_types=1);

namespace App\Admin\Data;

use InvalidArgumentException;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function in_array;
use function sprintf;

/**
 * Country calling codes for all sovereign states recognized by the United Nations in 2026
 * (193 member states plus Vatican City and Palestine as observer states), plus Kosovo and Taiwan,
 * sorted alphabetically by country name.
 */
final class WorldCountryCodes
{
    public const DEFAULT_CHOICE_KEY = 'alpha3';

    /** @var list<'name'|'alpha2'|'alpha3'> */
    public const CHOICE_KEYS = ['name', 'alpha2', 'alpha3'];

    public static function configureChoiceKeyOption(OptionsResolver $resolver, string $option = 'country_choice_key'): void
    {
        $resolver->setDefault($option, self::DEFAULT_CHOICE_KEY);
        $resolver->setAllowedTypes($option, 'string');
        $resolver->setAllowedValues($option, self::CHOICE_KEYS);
    }

    /**
     * @param 'name'|'alpha2'|'alpha3' $key
     *
     * @return array<string, int> display label => numeric country code (without +)
     */
    public static function formChoices(string $key = self::DEFAULT_CHOICE_KEY): array
    {
        $choices = [];

        foreach (self::countryPhoneCodes($key) as $label => $code) {
            $choices[sprintf('%s (+%d)', $label, $code)] = $code;
        }

        return $choices;
    }

    public static function format(int $countryCode): string
    {
        foreach (self::countryPhoneCodes('name') as $name => $code) {
            if ($code === $countryCode) {
                return sprintf('%s (+%d)', $name, $countryCode);
            }
        }

        return sprintf('+%d', $countryCode);
    }

    /**
     * @param 'name'|'alpha2'|'alpha3' $key
     *
     * @return array<string, string> numeric calling code => display label
     */
    public static function viewOptions(string $key = self::DEFAULT_CHOICE_KEY): array
    {
        $choices = self::formChoices($key);
        $labels = array_keys($choices);
        $codes = array_map(
            static fn (int $code): string => (string) $code,
            array_values($choices),
        );

        /** @var array<string, string> $viewOptions */
        $viewOptions = array_combine($codes, $labels);

        return $viewOptions;
    }

    /**
     * @return array<string, int> country identifier => numeric calling code (without +)
     */
    private static function countryPhoneCodes(string $key = self::DEFAULT_CHOICE_KEY): array
    {
        if (!in_array($key, self::CHOICE_KEYS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid country key "%s". Expected "name", "alpha2" or "alpha3".', $key)
            );
        }

        $choices = [];

        foreach (self::countries() as $country) {
            $choices[$country[$key]] = $country['code'];
        }

        return $choices;
    }

    /**
     * @return list<array{name: string, alpha2: string, alpha3: string, code: int}>
     */
    private static function countries(): array
    {
        return [
            ['name' => 'Afganistán', 'alpha2' => 'AF', 'alpha3' => 'AFG', 'code' => 93],
            ['name' => 'Albania', 'alpha2' => 'AL', 'alpha3' => 'ALB', 'code' => 355],
            ['name' => 'Alemania', 'alpha2' => 'DE', 'alpha3' => 'DEU', 'code' => 49],
            ['name' => 'Andorra', 'alpha2' => 'AD', 'alpha3' => 'AND', 'code' => 376],
            ['name' => 'Angola', 'alpha2' => 'AO', 'alpha3' => 'AGO', 'code' => 244],
            ['name' => 'Antigua y Barbuda', 'alpha2' => 'AG', 'alpha3' => 'ATG', 'code' => 1268],
            ['name' => 'Arabia Saudita', 'alpha2' => 'SA', 'alpha3' => 'SAU', 'code' => 966],
            ['name' => 'Argelia', 'alpha2' => 'DZ', 'alpha3' => 'DZA', 'code' => 213],
            ['name' => 'Argentina', 'alpha2' => 'AR', 'alpha3' => 'ARG', 'code' => 54],
            ['name' => 'Armenia', 'alpha2' => 'AM', 'alpha3' => 'ARM', 'code' => 374],
            ['name' => 'Australia', 'alpha2' => 'AU', 'alpha3' => 'AUS', 'code' => 61],
            ['name' => 'Austria', 'alpha2' => 'AT', 'alpha3' => 'AUT', 'code' => 43],
            ['name' => 'Azerbaiyán', 'alpha2' => 'AZ', 'alpha3' => 'AZE', 'code' => 994],
            ['name' => 'Bahamas', 'alpha2' => 'BS', 'alpha3' => 'BHS', 'code' => 1242],
            ['name' => 'Bangladés', 'alpha2' => 'BD', 'alpha3' => 'BGD', 'code' => 880],
            ['name' => 'Barbados', 'alpha2' => 'BB', 'alpha3' => 'BRB', 'code' => 1246],
            ['name' => 'Baréin', 'alpha2' => 'BH', 'alpha3' => 'BHR', 'code' => 973],
            ['name' => 'Bélgica', 'alpha2' => 'BE', 'alpha3' => 'BEL', 'code' => 32],
            ['name' => 'Belice', 'alpha2' => 'BZ', 'alpha3' => 'BLZ', 'code' => 501],
            ['name' => 'Benín', 'alpha2' => 'BJ', 'alpha3' => 'BEN', 'code' => 229],
            ['name' => 'Bielorrusia', 'alpha2' => 'BY', 'alpha3' => 'BLR', 'code' => 375],
            ['name' => 'Birmania', 'alpha2' => 'MM', 'alpha3' => 'MMR', 'code' => 95],
            ['name' => 'Bolivia', 'alpha2' => 'BO', 'alpha3' => 'BOL', 'code' => 591],
            ['name' => 'Bosnia y Herzegovina', 'alpha2' => 'BA', 'alpha3' => 'BIH', 'code' => 387],
            ['name' => 'Botsuana', 'alpha2' => 'BW', 'alpha3' => 'BWA', 'code' => 267],
            ['name' => 'Brasil', 'alpha2' => 'BR', 'alpha3' => 'BRA', 'code' => 55],
            ['name' => 'Brunéi', 'alpha2' => 'BN', 'alpha3' => 'BRN', 'code' => 673],
            ['name' => 'Bulgaria', 'alpha2' => 'BG', 'alpha3' => 'BGR', 'code' => 359],
            ['name' => 'Burkina Faso', 'alpha2' => 'BF', 'alpha3' => 'BFA', 'code' => 226],
            ['name' => 'Burundi', 'alpha2' => 'BI', 'alpha3' => 'BDI', 'code' => 257],
            ['name' => 'Bután', 'alpha2' => 'BT', 'alpha3' => 'BTN', 'code' => 975],
            ['name' => 'Cabo Verde', 'alpha2' => 'CV', 'alpha3' => 'CPV', 'code' => 238],
            ['name' => 'Camboya', 'alpha2' => 'KH', 'alpha3' => 'KHM', 'code' => 855],
            ['name' => 'Camerún', 'alpha2' => 'CM', 'alpha3' => 'CMR', 'code' => 237],
            ['name' => 'Canadá', 'alpha2' => 'CA', 'alpha3' => 'CAN', 'code' => 1],
            ['name' => 'Catar', 'alpha2' => 'QA', 'alpha3' => 'QAT', 'code' => 974],
            ['name' => 'Chad', 'alpha2' => 'TD', 'alpha3' => 'TCD', 'code' => 235],
            ['name' => 'Chile', 'alpha2' => 'CL', 'alpha3' => 'CHL', 'code' => 56],
            ['name' => 'China', 'alpha2' => 'CN', 'alpha3' => 'CHN', 'code' => 86],
            ['name' => 'Chipre', 'alpha2' => 'CY', 'alpha3' => 'CYP', 'code' => 357],
            ['name' => 'Ciudad del Vaticano', 'alpha2' => 'VA', 'alpha3' => 'VAT', 'code' => 379],
            ['name' => 'Colombia', 'alpha2' => 'CO', 'alpha3' => 'COL', 'code' => 57],
            ['name' => 'Comoras', 'alpha2' => 'KM', 'alpha3' => 'COM', 'code' => 269],
            ['name' => 'Corea del Norte', 'alpha2' => 'KP', 'alpha3' => 'PRK', 'code' => 850],
            ['name' => 'Corea del Sur', 'alpha2' => 'KR', 'alpha3' => 'KOR', 'code' => 82],
            ['name' => 'Costa de Marfil', 'alpha2' => 'CI', 'alpha3' => 'CIV', 'code' => 225],
            ['name' => 'Costa Rica', 'alpha2' => 'CR', 'alpha3' => 'CRI', 'code' => 506],
            ['name' => 'Croacia', 'alpha2' => 'HR', 'alpha3' => 'HRV', 'code' => 385],
            ['name' => 'Cuba', 'alpha2' => 'CU', 'alpha3' => 'CUB', 'code' => 53],
            ['name' => 'Dinamarca', 'alpha2' => 'DK', 'alpha3' => 'DNK', 'code' => 45],
            ['name' => 'Dominica', 'alpha2' => 'DM', 'alpha3' => 'DMA', 'code' => 1767],
            ['name' => 'Ecuador', 'alpha2' => 'EC', 'alpha3' => 'ECU', 'code' => 593],
            ['name' => 'Egipto', 'alpha2' => 'EG', 'alpha3' => 'EGY', 'code' => 20],
            ['name' => 'El Salvador', 'alpha2' => 'SV', 'alpha3' => 'SLV', 'code' => 503],
            ['name' => 'Emiratos Árabes Unidos', 'alpha2' => 'AE', 'alpha3' => 'ARE', 'code' => 971],
            ['name' => 'Eritrea', 'alpha2' => 'ER', 'alpha3' => 'ERI', 'code' => 291],
            ['name' => 'Eslovaquia', 'alpha2' => 'SK', 'alpha3' => 'SVK', 'code' => 421],
            ['name' => 'Eslovenia', 'alpha2' => 'SI', 'alpha3' => 'SVN', 'code' => 386],
            ['name' => 'España', 'alpha2' => 'ES', 'alpha3' => 'ESP', 'code' => 34],
            ['name' => 'Estados Unidos', 'alpha2' => 'US', 'alpha3' => 'USA', 'code' => 1],
            ['name' => 'Estonia', 'alpha2' => 'EE', 'alpha3' => 'EST', 'code' => 372],
            ['name' => 'Eswatini', 'alpha2' => 'SZ', 'alpha3' => 'SWZ', 'code' => 268],
            ['name' => 'Etiopía', 'alpha2' => 'ET', 'alpha3' => 'ETH', 'code' => 251],
            ['name' => 'Filipinas', 'alpha2' => 'PH', 'alpha3' => 'PHL', 'code' => 63],
            ['name' => 'Finlandia', 'alpha2' => 'FI', 'alpha3' => 'FIN', 'code' => 358],
            ['name' => 'Fiyi', 'alpha2' => 'FJ', 'alpha3' => 'FJI', 'code' => 679],
            ['name' => 'Francia', 'alpha2' => 'FR', 'alpha3' => 'FRA', 'code' => 33],
            ['name' => 'Gabón', 'alpha2' => 'GA', 'alpha3' => 'GAB', 'code' => 241],
            ['name' => 'Gambia', 'alpha2' => 'GM', 'alpha3' => 'GMB', 'code' => 220],
            ['name' => 'Georgia', 'alpha2' => 'GE', 'alpha3' => 'GEO', 'code' => 995],
            ['name' => 'Ghana', 'alpha2' => 'GH', 'alpha3' => 'GHA', 'code' => 233],
            ['name' => 'Granada', 'alpha2' => 'GD', 'alpha3' => 'GRD', 'code' => 1473],
            ['name' => 'Grecia', 'alpha2' => 'GR', 'alpha3' => 'GRC', 'code' => 30],
            ['name' => 'Guatemala', 'alpha2' => 'GT', 'alpha3' => 'GTM', 'code' => 502],
            ['name' => 'Guinea', 'alpha2' => 'GN', 'alpha3' => 'GIN', 'code' => 224],
            ['name' => 'Guinea Ecuatorial', 'alpha2' => 'GQ', 'alpha3' => 'GNQ', 'code' => 240],
            ['name' => 'Guinea-Bisáu', 'alpha2' => 'GW', 'alpha3' => 'GNB', 'code' => 245],
            ['name' => 'Guyana', 'alpha2' => 'GY', 'alpha3' => 'GUY', 'code' => 592],
            ['name' => 'Haití', 'alpha2' => 'HT', 'alpha3' => 'HTI', 'code' => 509],
            ['name' => 'Honduras', 'alpha2' => 'HN', 'alpha3' => 'HND', 'code' => 504],
            ['name' => 'Hungría', 'alpha2' => 'HU', 'alpha3' => 'HUN', 'code' => 36],
            ['name' => 'India', 'alpha2' => 'IN', 'alpha3' => 'IND', 'code' => 91],
            ['name' => 'Indonesia', 'alpha2' => 'ID', 'alpha3' => 'IDN', 'code' => 62],
            ['name' => 'Irak', 'alpha2' => 'IQ', 'alpha3' => 'IRQ', 'code' => 964],
            ['name' => 'Irán', 'alpha2' => 'IR', 'alpha3' => 'IRN', 'code' => 98],
            ['name' => 'Irlanda', 'alpha2' => 'IE', 'alpha3' => 'IRL', 'code' => 353],
            ['name' => 'Islandia', 'alpha2' => 'IS', 'alpha3' => 'ISL', 'code' => 354],
            ['name' => 'Islas Marshall', 'alpha2' => 'MH', 'alpha3' => 'MHL', 'code' => 692],
            ['name' => 'Islas Salomón', 'alpha2' => 'SB', 'alpha3' => 'SLB', 'code' => 677],
            ['name' => 'Israel', 'alpha2' => 'IL', 'alpha3' => 'ISR', 'code' => 972],
            ['name' => 'Italia', 'alpha2' => 'IT', 'alpha3' => 'ITA', 'code' => 39],
            ['name' => 'Jamaica', 'alpha2' => 'JM', 'alpha3' => 'JAM', 'code' => 1876],
            ['name' => 'Japón', 'alpha2' => 'JP', 'alpha3' => 'JPN', 'code' => 81],
            ['name' => 'Jordania', 'alpha2' => 'JO', 'alpha3' => 'JOR', 'code' => 962],
            ['name' => 'Kazajistán', 'alpha2' => 'KZ', 'alpha3' => 'KAZ', 'code' => 7],
            ['name' => 'Kenia', 'alpha2' => 'KE', 'alpha3' => 'KEN', 'code' => 254],
            ['name' => 'Kirguistán', 'alpha2' => 'KG', 'alpha3' => 'KGZ', 'code' => 996],
            ['name' => 'Kiribati', 'alpha2' => 'KI', 'alpha3' => 'KIR', 'code' => 686],
            ['name' => 'Kosovo', 'alpha2' => 'XK', 'alpha3' => 'XKX', 'code' => 383],
            ['name' => 'Kuwait', 'alpha2' => 'KW', 'alpha3' => 'KWT', 'code' => 965],
            ['name' => 'Laos', 'alpha2' => 'LA', 'alpha3' => 'LAO', 'code' => 856],
            ['name' => 'Lesoto', 'alpha2' => 'LS', 'alpha3' => 'LSO', 'code' => 266],
            ['name' => 'Letonia', 'alpha2' => 'LV', 'alpha3' => 'LVA', 'code' => 371],
            ['name' => 'Líbano', 'alpha2' => 'LB', 'alpha3' => 'LBN', 'code' => 961],
            ['name' => 'Liberia', 'alpha2' => 'LR', 'alpha3' => 'LBR', 'code' => 231],
            ['name' => 'Libia', 'alpha2' => 'LY', 'alpha3' => 'LBY', 'code' => 218],
            ['name' => 'Liechtenstein', 'alpha2' => 'LI', 'alpha3' => 'LIE', 'code' => 423],
            ['name' => 'Lituania', 'alpha2' => 'LT', 'alpha3' => 'LTU', 'code' => 370],
            ['name' => 'Luxemburgo', 'alpha2' => 'LU', 'alpha3' => 'LUX', 'code' => 352],
            ['name' => 'Macedonia del Norte', 'alpha2' => 'MK', 'alpha3' => 'MKD', 'code' => 389],
            ['name' => 'Madagascar', 'alpha2' => 'MG', 'alpha3' => 'MDG', 'code' => 261],
            ['name' => 'Malasia', 'alpha2' => 'MY', 'alpha3' => 'MYS', 'code' => 60],
            ['name' => 'Malaui', 'alpha2' => 'MW', 'alpha3' => 'MWI', 'code' => 265],
            ['name' => 'Maldivas', 'alpha2' => 'MV', 'alpha3' => 'MDV', 'code' => 960],
            ['name' => 'Malí', 'alpha2' => 'ML', 'alpha3' => 'MLI', 'code' => 223],
            ['name' => 'Malta', 'alpha2' => 'MT', 'alpha3' => 'MLT', 'code' => 356],
            ['name' => 'Marruecos', 'alpha2' => 'MA', 'alpha3' => 'MAR', 'code' => 212],
            ['name' => 'Mauricio', 'alpha2' => 'MU', 'alpha3' => 'MUS', 'code' => 230],
            ['name' => 'Mauritania', 'alpha2' => 'MR', 'alpha3' => 'MRT', 'code' => 222],
            ['name' => 'México', 'alpha2' => 'MX', 'alpha3' => 'MEX', 'code' => 52],
            ['name' => 'Micronesia', 'alpha2' => 'FM', 'alpha3' => 'FSM', 'code' => 691],
            ['name' => 'Moldavia', 'alpha2' => 'MD', 'alpha3' => 'MDA', 'code' => 373],
            ['name' => 'Mónaco', 'alpha2' => 'MC', 'alpha3' => 'MCO', 'code' => 377],
            ['name' => 'Mongolia', 'alpha2' => 'MN', 'alpha3' => 'MNG', 'code' => 976],
            ['name' => 'Montenegro', 'alpha2' => 'ME', 'alpha3' => 'MNE', 'code' => 382],
            ['name' => 'Mozambique', 'alpha2' => 'MZ', 'alpha3' => 'MOZ', 'code' => 258],
            ['name' => 'Namibia', 'alpha2' => 'NA', 'alpha3' => 'NAM', 'code' => 264],
            ['name' => 'Nauru', 'alpha2' => 'NR', 'alpha3' => 'NRU', 'code' => 674],
            ['name' => 'Nepal', 'alpha2' => 'NP', 'alpha3' => 'NPL', 'code' => 977],
            ['name' => 'Nicaragua', 'alpha2' => 'NI', 'alpha3' => 'NIC', 'code' => 505],
            ['name' => 'Níger', 'alpha2' => 'NE', 'alpha3' => 'NER', 'code' => 227],
            ['name' => 'Nigeria', 'alpha2' => 'NG', 'alpha3' => 'NGA', 'code' => 234],
            ['name' => 'Noruega', 'alpha2' => 'NO', 'alpha3' => 'NOR', 'code' => 47],
            ['name' => 'Nueva Zelanda', 'alpha2' => 'NZ', 'alpha3' => 'NZL', 'code' => 64],
            ['name' => 'Omán', 'alpha2' => 'OM', 'alpha3' => 'OMN', 'code' => 968],
            ['name' => 'Países Bajos', 'alpha2' => 'NL', 'alpha3' => 'NLD', 'code' => 31],
            ['name' => 'Pakistán', 'alpha2' => 'PK', 'alpha3' => 'PAK', 'code' => 92],
            ['name' => 'Palaos', 'alpha2' => 'PW', 'alpha3' => 'PLW', 'code' => 680],
            ['name' => 'Palestina', 'alpha2' => 'PS', 'alpha3' => 'PSE', 'code' => 970],
            ['name' => 'Panamá', 'alpha2' => 'PA', 'alpha3' => 'PAN', 'code' => 507],
            ['name' => 'Papúa Nueva Guinea', 'alpha2' => 'PG', 'alpha3' => 'PNG', 'code' => 675],
            ['name' => 'Paraguay', 'alpha2' => 'PY', 'alpha3' => 'PRY', 'code' => 595],
            ['name' => 'Perú', 'alpha2' => 'PE', 'alpha3' => 'PER', 'code' => 51],
            ['name' => 'Polonia', 'alpha2' => 'PL', 'alpha3' => 'POL', 'code' => 48],
            ['name' => 'Portugal', 'alpha2' => 'PT', 'alpha3' => 'PRT', 'code' => 351],
            ['name' => 'Reino Unido', 'alpha2' => 'GB', 'alpha3' => 'GBR', 'code' => 44],
            ['name' => 'República Centroafricana', 'alpha2' => 'CF', 'alpha3' => 'CAF', 'code' => 236],
            ['name' => 'República Checa', 'alpha2' => 'CZ', 'alpha3' => 'CZE', 'code' => 420],
            ['name' => 'República del Congo', 'alpha2' => 'CG', 'alpha3' => 'COG', 'code' => 242],
            ['name' => 'República Democrática del Congo', 'alpha2' => 'CD', 'alpha3' => 'COD', 'code' => 243],
            ['name' => 'República Dominicana', 'alpha2' => 'DO', 'alpha3' => 'DOM', 'code' => 1809],
            ['name' => 'Ruanda', 'alpha2' => 'RW', 'alpha3' => 'RWA', 'code' => 250],
            ['name' => 'Rumania', 'alpha2' => 'RO', 'alpha3' => 'ROU', 'code' => 40],
            ['name' => 'Rusia', 'alpha2' => 'RU', 'alpha3' => 'RUS', 'code' => 7],
            ['name' => 'Samoa', 'alpha2' => 'WS', 'alpha3' => 'WSM', 'code' => 685],
            ['name' => 'San Cristóbal y Nieves', 'alpha2' => 'KN', 'alpha3' => 'KNA', 'code' => 1869],
            ['name' => 'San Marino', 'alpha2' => 'SM', 'alpha3' => 'SMR', 'code' => 378],
            ['name' => 'San Vicente y las Granadinas', 'alpha2' => 'VC', 'alpha3' => 'VCT', 'code' => 1784],
            ['name' => 'Santa Lucía', 'alpha2' => 'LC', 'alpha3' => 'LCA', 'code' => 1758],
            ['name' => 'Santo Tomé y Príncipe', 'alpha2' => 'ST', 'alpha3' => 'STP', 'code' => 239],
            ['name' => 'Senegal', 'alpha2' => 'SN', 'alpha3' => 'SEN', 'code' => 221],
            ['name' => 'Serbia', 'alpha2' => 'RS', 'alpha3' => 'SRB', 'code' => 381],
            ['name' => 'Seychelles', 'alpha2' => 'SC', 'alpha3' => 'SYC', 'code' => 248],
            ['name' => 'Sierra Leona', 'alpha2' => 'SL', 'alpha3' => 'SLE', 'code' => 232],
            ['name' => 'Singapur', 'alpha2' => 'SG', 'alpha3' => 'SGP', 'code' => 65],
            ['name' => 'Siria', 'alpha2' => 'SY', 'alpha3' => 'SYR', 'code' => 963],
            ['name' => 'Somalia', 'alpha2' => 'SO', 'alpha3' => 'SOM', 'code' => 252],
            ['name' => 'Sri Lanka', 'alpha2' => 'LK', 'alpha3' => 'LKA', 'code' => 94],
            ['name' => 'Sudáfrica', 'alpha2' => 'ZA', 'alpha3' => 'ZAF', 'code' => 27],
            ['name' => 'Sudán', 'alpha2' => 'SD', 'alpha3' => 'SDN', 'code' => 249],
            ['name' => 'Sudán del Sur', 'alpha2' => 'SS', 'alpha3' => 'SSD', 'code' => 211],
            ['name' => 'Suecia', 'alpha2' => 'SE', 'alpha3' => 'SWE', 'code' => 46],
            ['name' => 'Suiza', 'alpha2' => 'CH', 'alpha3' => 'CHE', 'code' => 41],
            ['name' => 'Surinam', 'alpha2' => 'SR', 'alpha3' => 'SUR', 'code' => 597],
            ['name' => 'Tailandia', 'alpha2' => 'TH', 'alpha3' => 'THA', 'code' => 66],
            ['name' => 'Tanzania', 'alpha2' => 'TZ', 'alpha3' => 'TZA', 'code' => 255],
            ['name' => 'Taiwán', 'alpha2' => 'TW', 'alpha3' => 'TWN', 'code' => 886],
            ['name' => 'Tayikistán', 'alpha2' => 'TJ', 'alpha3' => 'TJK', 'code' => 992],
            ['name' => 'Timor Oriental', 'alpha2' => 'TL', 'alpha3' => 'TLS', 'code' => 670],
            ['name' => 'Togo', 'alpha2' => 'TG', 'alpha3' => 'TGO', 'code' => 228],
            ['name' => 'Tonga', 'alpha2' => 'TO', 'alpha3' => 'TON', 'code' => 676],
            ['name' => 'Trinidad y Tobago', 'alpha2' => 'TT', 'alpha3' => 'TTO', 'code' => 1868],
            ['name' => 'Túnez', 'alpha2' => 'TN', 'alpha3' => 'TUN', 'code' => 216],
            ['name' => 'Turkmenistán', 'alpha2' => 'TM', 'alpha3' => 'TKM', 'code' => 993],
            ['name' => 'Turquía', 'alpha2' => 'TR', 'alpha3' => 'TUR', 'code' => 90],
            ['name' => 'Tuvalu', 'alpha2' => 'TV', 'alpha3' => 'TUV', 'code' => 688],
            ['name' => 'Ucrania', 'alpha2' => 'UA', 'alpha3' => 'UKR', 'code' => 380],
            ['name' => 'Uganda', 'alpha2' => 'UG', 'alpha3' => 'UGA', 'code' => 256],
            ['name' => 'Uruguay', 'alpha2' => 'UY', 'alpha3' => 'URY', 'code' => 598],
            ['name' => 'Uzbekistán', 'alpha2' => 'UZ', 'alpha3' => 'UZB', 'code' => 998],
            ['name' => 'Vanuatu', 'alpha2' => 'VU', 'alpha3' => 'VUT', 'code' => 678],
            ['name' => 'Venezuela', 'alpha2' => 'VE', 'alpha3' => 'VEN', 'code' => 58],
            ['name' => 'Vietnam', 'alpha2' => 'VN', 'alpha3' => 'VNM', 'code' => 84],
            ['name' => 'Yemen', 'alpha2' => 'YE', 'alpha3' => 'YEM', 'code' => 967],
            ['name' => 'Yibuti', 'alpha2' => 'DJ', 'alpha3' => 'DJI', 'code' => 253],
            ['name' => 'Zambia', 'alpha2' => 'ZM', 'alpha3' => 'ZMB', 'code' => 260],
            ['name' => 'Zimbabue', 'alpha2' => 'ZW', 'alpha3' => 'ZWE', 'code' => 263],
        ];
    }
}
