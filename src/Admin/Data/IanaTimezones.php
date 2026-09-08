<?php

declare(strict_types=1);

namespace App\Admin\Data;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Timezones;

use function in_array;
use function sprintf;

/**
 * IANA timezone identifiers for forms and labels.
 */
final class IanaTimezones
{
    /**
     * @var array<string, string>|null identifier => label
     */
    private static ?array $options = null;

    /**
     * @return array<string, string> label => identifier
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::options() as $identifier => $label) {
            $choices[$label] = $identifier;
        }

        return $choices;
    }

    /**
     * @return array<string, string> identifier => label
     */
    public static function options(): array
    {
        if (null !== self::$options) {
            return self::$options;
        }

        $options = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $options[$identifier] = self::label($identifier);
        }

        self::$options = $options;

        return $options;
    }

    public static function label(string $identifier): string
    {
        try {
            $timezone = new DateTimeZone($identifier);
        } catch (Exception) {
            return $identifier;
        }

        $offset = new DateTimeImmutable('now', $timezone)->format('P');
        $localizedName = self::localizedName($identifier);

        if (null !== $localizedName) {
            return sprintf('(UTC%s) %s — %s', $offset, $localizedName, $identifier);
        }

        return sprintf('(UTC%s) %s', $offset, $identifier);
    }

    public static function exists(string $identifier): bool
    {
        return in_array($identifier, DateTimeZone::listIdentifiers(), true);
    }

    private static function localizedName(string $identifier): ?string
    {
        if (!Timezones::exists($identifier)) {
            return null;
        }

        try {
            $name = Timezones::getName($identifier, 'es');
        } catch (MissingResourceException) {
            return null;
        }

        if ('' === $name || $identifier === $name) {
            return null;
        }

        return $name;
    }
}
