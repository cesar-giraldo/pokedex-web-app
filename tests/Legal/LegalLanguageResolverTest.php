<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegalLanguageResolver::class)]
#[Group('unit')]
final class LegalLanguageResolverTest extends TestCase
{
    public function testPreferredLanguageFallsBackWhenTheStoredLanguageIsDisabled(): void
    {
        $settings = $this->settings(['es', 'en'], 'es');
        $user = new User()->setPreferredLanguage(SupportedLanguage::French);
        $resolver = new LegalLanguageResolver();

        self::assertSame(SupportedLanguage::Spanish, $resolver->preferredForUser($user, $settings));
    }

    public function testPreferredLanguageUsesTheStoredLanguageWhenItIsEnabled(): void
    {
        $settings = $this->settings(['es', 'en'], 'es');
        $user = new User()->setPreferredLanguage(SupportedLanguage::English);
        $resolver = new LegalLanguageResolver();

        self::assertSame(SupportedLanguage::English, $resolver->preferredForUser($user, $settings));
    }

    public function testNullPreferredLanguageUsesTheWebsiteDefault(): void
    {
        $settings = $this->settings(['es', 'en'], 'en');
        $resolver = new LegalLanguageResolver();

        self::assertSame(SupportedLanguage::English, $resolver->preferredForUser(new User(), $settings));
    }

    public function testNullPreferredLanguageSkipsADisabledWebsiteDefault(): void
    {
        $settings = $this->settings(['en', 'fr'], 'es');
        $resolver = new LegalLanguageResolver();

        self::assertSame(SupportedLanguage::English, $resolver->preferredForUser(new User(), $settings));
    }

    public function testContentFallsBackToDefaultLanguageWhenRequestedTranslationIsMissing(): void
    {
        $settings = $this->settings(['es', 'en', 'fr'], 'es');
        $version = new LegalDocumentVersion(new LegalDocument(LegalDocumentType::PrivacyPolicy), 1);
        $spanish = new LegalDocumentVersionTranslation($version, 'es');
        $spanish->setTitle('Privacidad')->setContentHtml('<p>Hola</p>')->setAcceptanceLabel('Acepto');

        $resolver = new LegalLanguageResolver();
        $translation = $resolver->resolveTranslation($version, SupportedLanguage::French, $settings);

        self::assertSame($spanish, $translation);
    }

    public function testUnknownPublicLanguageUsesWebsiteDefault(): void
    {
        $resolver = new LegalLanguageResolver();

        self::assertSame(
            SupportedLanguage::English,
            $resolver->preferredFromCode('de', $this->settings(['en'], 'en')),
        );
    }

    /**
     * @param list<string> $enabled
     */
    private function settings(array $enabled, string $default): GeneralSettings
    {
        return GeneralSettings::createWithDefaults()
            ->setEnabledLanguages($enabled)
            ->setWebsiteDefaultLanguage($default);
    }
}
