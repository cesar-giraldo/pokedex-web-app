<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum TimeFormat: string
{
    case Hour12 = '12h';
    case Hour24 = '24h';

    public function label(): string
    {
        return match ($this) {
            self::Hour12 => '12 horas (1:00 p. m.)',
            self::Hour24 => '24 horas (13:00)',
        };
    }

    public function isHour12(): bool
    {
        return self::Hour12 === $this;
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $format) {
            $choices[$format->label()] = $format->value;
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $format) {
            $options[$format->value] = $format->label();
        }

        return $options;
    }
}
