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
            self::Spanish => 'es - Español',
            self::English => 'en - English',
            self::Portuguese => 'pt - Português',
            self::French => 'fr - Français',
        };
    }

    /**
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
