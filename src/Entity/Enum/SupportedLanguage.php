<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum SupportedLanguage: string
{
    case Spanish = 'es';
    case English = 'en';
    case Portuguese = 'pt';
    case French = 'fr';

    public function label(): string
    {
        return match ($this) {
            self::Spanish => 'Español - es',
            self::English => 'English - en',
            self::Portuguese => 'Português - pt',
            self::French => 'Français - fr',
        };
    }

    /**
     * Opciones para Symfony ChoiceType (label => value).
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $language) {
            $choices[$language->label()] = $language->value;
        }

        return $choices;
    }

    /**
     * Opciones para componentes Twig del admin (value => label).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $language) {
            $options[$language->value] = $language->label();
        }

        return $options;
    }

    /**
     * @param list<string> $values
     *
     * @return list<self>
     */
    public static function fromStoredValues(array $values): array
    {
        $languages = [];

        foreach ($values as $value) {
            $language = self::tryFrom($value);

            if (null !== $language) {
                $languages[] = $language;
            }
        }

        return $languages;
    }
}
