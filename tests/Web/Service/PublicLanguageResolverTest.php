<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use App\Web\Service\PublicLanguageResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublicLanguageResolver::class)]
#[Group('unit')]
final class PublicLanguageResolverTest extends TestCase
{
    public function testDefaultLanguageStaysWhenItIsEnabled(): void
    {
        $resolver = new PublicLanguageResolver();
        $settings = $this->settings(['en', 'es'], 'en');

        self::assertSame(SupportedLanguage::English, $resolver->defaultLanguage($settings));
    }

    public function testDefaultLanguageFallsBackToTheFirstEnabledLanguage(): void
    {
        $resolver = new PublicLanguageResolver();
        $settings = $this->settings(['pt', 'es'], 'fr');

        self::assertSame(SupportedLanguage::Portuguese, $resolver->defaultLanguage($settings));
    }

    public function testEmptyConfigurationFallsBackToSpanish(): void
    {
        $resolver = new PublicLanguageResolver();
        $settings = $this->settings([], 'fr');

        self::assertSame(SupportedLanguage::Spanish, $resolver->defaultLanguage($settings));
        self::assertFalse($resolver->isEnabled('es', $settings));
    }

    public function testResolveRejectsUnknownOrDisabledCodes(): void
    {
        $resolver = new PublicLanguageResolver();
        $settings = $this->settings(['es'], 'es');

        self::assertSame(SupportedLanguage::Spanish, $resolver->resolve('zz', $settings));
        self::assertSame(SupportedLanguage::Spanish, $resolver->resolve('fr', $settings));
        self::assertSame(SupportedLanguage::Spanish, $resolver->resolve('es', $settings));
    }

    /**
     * @param list<string> $enabled
     */
    private function settings(array $enabled, string $default): GeneralSettings
    {
        return new GeneralSettings()
            ->setEnabledLanguages($enabled)
            ->setWebsiteDefaultLanguage($default);
    }
}
