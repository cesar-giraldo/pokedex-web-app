<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Legal\LegalLanguageResolver;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Web\Service\SignupLegalCatalog;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignupLegalCatalog::class)]
#[Group('unit')]
final class SignupLegalCatalogTest extends TestCase
{
    public function testNoOfferIsReturnedWithoutPublishedDocuments(): void
    {
        $offers = $this->catalog([])->available(SupportedLanguage::Spanish);

        self::assertSame([], $offers);
    }

    public function testOnlyThePublishedDocumentWithATranslationIsOffered(): void
    {
        $offers = $this->catalog([
            LegalDocumentType::TermsOfUse->value => $this->published(LegalDocumentType::TermsOfUse),
        ])->available(SupportedLanguage::Spanish);

        self::assertCount(1, $offers);
        self::assertTrue($offers[0]->isTerms());
        self::assertSame('app_public_terms', $offers[0]->routeName());
    }

    public function testBothDocumentsAreOfferedWithPrivacyFirst(): void
    {
        $offers = $this->catalog([
            LegalDocumentType::TermsOfUse->value => $this->published(LegalDocumentType::TermsOfUse),
            LegalDocumentType::PrivacyPolicy->value => $this->published(LegalDocumentType::PrivacyPolicy),
        ])->available(SupportedLanguage::Spanish);

        self::assertCount(2, $offers);
        self::assertTrue($offers[0]->isPrivacy());
        self::assertTrue($offers[1]->isTerms());
    }

    public function testAPublishedDocumentWithoutACompleteTranslationIsHidden(): void
    {
        $document = new LegalDocument(LegalDocumentType::PrivacyPolicy);
        $version = new LegalDocumentVersion($document, 1);
        new LegalDocumentVersionTranslation($version, 'es');
        $version->publish(true, 'Aviso', 'Mensaje', new DateTime());

        $offers = $this->catalog([
            LegalDocumentType::PrivacyPolicy->value => $version,
        ])->available(SupportedLanguage::Spanish);

        self::assertSame([], $offers);
    }

    /**
     * @param array<string, LegalDocumentVersion> $published
     */
    private function catalog(array $published): SignupLegalCatalog
    {
        $documents = $this->createMock(LegalDocumentRepository::class);
        $documents->method('findOneByType')->willReturnCallback(
            static function (LegalDocumentType $type) use ($published): ?LegalDocument {
                return isset($published[$type->value]) ? $published[$type->value]->getDocument() : null;
            },
        );
        $versions = $this->createMock(LegalDocumentVersionRepository::class);
        $versions->method('findPublished')->willReturnCallback(
            static function (LegalDocument $document) use ($published): ?LegalDocumentVersion {
                return $published[$document->getType()->value] ?? null;
            },
        );
        $settingsRepository = $this->createStub(GeneralSettingsRepository::class);
        $settingsRepository->method('findSingleton')->willReturn(GeneralSettings::createWithDefaults());
        $settings = new GeneralSettingsProvider($settingsRepository);

        return new SignupLegalCatalog($documents, $versions, new LegalLanguageResolver(), $settings);
    }

    private function published(LegalDocumentType $type): LegalDocumentVersion
    {
        $document = new LegalDocument($type);
        $version = new LegalDocumentVersion($document, 1);
        $translation = new LegalDocumentVersionTranslation($version, 'es');
        $translation->setTitle('Documento');
        $translation->setContentHtml('<p>Texto legal</p>');
        $translation->setAcceptanceLabel('Acepto');
        $version->publish(true, 'Aviso', 'Mensaje', new DateTime());

        return $version;
    }
}
