<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Legal\LegalDocumentPublisher;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Web\Service\PublicLanguageResolver;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class LegalControllerTest extends WebTestCase
{
    public function testUnknownVersionReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/legal/privacy_policy/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLegacyTermsUrlRedirectsUsingLanguageFallback(): void
    {
        $client = static::createClient();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();
        $document = $documents->findOneByType(LegalDocumentType::TermsOfUse);
        self::assertNotNull($document);

        /** @var LegalDocumentVersionRepository $versions */
        $versions = static::getContainer()->get(LegalDocumentVersionRepository::class);
        $published = $versions->findPublished($document);
        /** @var GeneralSettingsProvider $settingsProvider */
        $settingsProvider = static::getContainer()->get(GeneralSettingsProvider::class);
        /** @var PublicLanguageResolver $languages */
        $languages = static::getContainer()->get(PublicLanguageResolver::class);
        $locale = $languages->defaultLanguage($settingsProvider->get())->value;

        $client->request('GET', '/legal/terms_of_use?lang=zz');

        self::assertResponseRedirects('/' . $locale . '/terminos');
        $client->followRedirect();

        if (!$published instanceof LegalDocumentVersion) {
            self::assertResponseStatusCodeSame(404);

            return;
        }

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
    }
}
