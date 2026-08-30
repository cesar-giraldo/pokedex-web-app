<?php

declare(strict_types=1);

namespace App\Tests\Entity\Enum;

use App\Entity\Enum\SupportedLanguage;
use PHPUnit\Framework\TestCase;

final class SupportedLanguageTest extends TestCase
{
    public function testProvidesLocalizedLabels(): void
    {
        self::assertSame('es - Español', SupportedLanguage::Spanish->label());
        self::assertSame('en - English', SupportedLanguage::English->label());
        self::assertSame('pt - Português', SupportedLanguage::Portuguese->label());
        self::assertSame('fr - Français', SupportedLanguage::French->label());
    }

    public function testBuildsChoicesForForms(): void
    {
        $choices = SupportedLanguage::choices();

        self::assertSame('es', $choices['es - Español']);
        self::assertSame('en', $choices['en - English']);
        self::assertCount(4, $choices);
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
