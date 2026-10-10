<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Entity\Enum\LegalDocumentType;
use App\Repository\LegalDocumentVersionRepository;
use App\Web\Service\PublicLegalAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublicLegalAvailability::class)]
#[Group('unit')]
final class PublicLegalAvailabilityTest extends TestCase
{
    public function testBothLinksStayAvailableWhenBothDocumentsArePublished(): void
    {
        $availability = $this->availability([
            LegalDocumentType::PrivacyPolicy,
            LegalDocumentType::TermsOfUse,
        ]);

        self::assertSame(['privacy' => true, 'terms' => true], $availability->links());
    }

    public function testOnlyThePublishedDocumentIsAvailable(): void
    {
        $availability = $this->availability([LegalDocumentType::TermsOfUse]);

        self::assertSame(['privacy' => false, 'terms' => true], $availability->links());
    }

    public function testNoLinksAreAvailableWithoutAPublishedDocument(): void
    {
        $availability = $this->availability([]);

        self::assertSame(['privacy' => false, 'terms' => false], $availability->links());
    }

    /**
     * @param list<LegalDocumentType> $published
     */
    private function availability(array $published): PublicLegalAvailability
    {
        $versions = $this->createMock(LegalDocumentVersionRepository::class);
        $versions->method('findPublishedDocumentTypes')->willReturn($published);

        return new PublicLegalAvailability($versions);
    }
}
