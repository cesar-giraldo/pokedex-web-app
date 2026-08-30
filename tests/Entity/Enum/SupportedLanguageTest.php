<?php

declare(strict_types=1);

namespace App\Tests\Entity\Enum;

use App\Entity\Enum\SupportedLanguage;
use PHPUnit\Framework\TestCase;

final class SupportedLanguageTest extends TestCase
{
    public function testProvidesLocalizedLabels(): void
    {
        self::assertSame('Español - es', SupportedLanguage::Spanish->label());
        self::assertSame('English - en', SupportedLanguage::English->label());
        self::assertSame('Português - pt', SupportedLanguage::Portuguese->label());
        self::assertSame('Français - fr', SupportedLanguage::French->label());
    }

    public function testBuildsChoicesForForms(): void
    {
        $choices = SupportedLanguage::choices();

        self::assertSame('es', $choices['Español - es']);
        self::assertSame('en', $choices['English - en']);
        self::assertCount(4, $choices);
    }

    public function testBuildsOptionsForComponents(): void
    {
        $options = SupportedLanguage::options();

        self::assertSame('Español - es', $options['es']);
        self::assertSame('English - en', $options['en']);
        self::assertCount(4, $options);
    }

    public function testFiltersStoredValues(): void
    {
        $languages = SupportedLanguage::fromStoredValues(['es', 'invalid', 'en']);

        self::assertSame(
            [SupportedLanguage::Spanish, SupportedLanguage::English],
            $languages,
        );
    }
}
