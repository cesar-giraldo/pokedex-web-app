<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Entity\Enum\LegalDocumentType;
use App\Repository\LegalDocumentVersionRepository;
use App\Web\Service\PublicLegalAvailability;
use App\Web\Service\PublicSitemapBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(PublicSitemapBuilder::class)]
#[Group('unit')]
final class PublicSitemapBuilderTest extends TestCase
{
    public function testSitemapOmitsLegalPagesWithoutAPublishedVersion(): void
    {
        $xml = $this->sitemap([LegalDocumentType::TermsOfUse]);

        self::assertStringNotContainsString('app_public_privacy', $xml);
        self::assertStringContainsString('app_public_terms', $xml);
        self::assertStringContainsString('app_public_home', $xml);
        self::assertStringContainsString('app_public_contact', $xml);
    }

    public function testSitemapOmitsTheLegalSectionWhenNothingIsPublished(): void
    {
        $xml = $this->sitemap([]);

        self::assertStringNotContainsString('app_public_privacy', $xml);
        self::assertStringNotContainsString('app_public_terms', $xml);
    }

    /**
     * @param list<LegalDocumentType> $published
     */
    private function sitemap(array $published): string
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn (string $route, array $params, int $referenceType): string => 'https://example.test/' . $params['_locale'] . '/' . $route,
        );
        $versions = $this->createMock(LegalDocumentVersionRepository::class);
        $versions->method('findPublishedDocumentTypes')->willReturn($published);

        return new PublicSitemapBuilder($urls, new PublicLegalAvailability($versions))->build(['es'], 'es', []);
    }
}
