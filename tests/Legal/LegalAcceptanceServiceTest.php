<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\UserStatus;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Legal\LegalAcceptanceService;
use App\Legal\LegalLanguageResolver;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserLegalAcceptanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegalAcceptanceService::class)]
#[Group('unit')]
final class LegalAcceptanceServiceTest extends TestCase
{
    public function testInactiveStatusesAreNeverPending(): void
    {
        $versions = $this->createMock(LegalDocumentVersionRepository::class);
        $versions->expects(self::never())->method('findPublishedRequiringAcceptance');

        $service = new LegalAcceptanceService(
            $this->createStub(EntityManagerInterface::class),
            $versions,
            $this->createStub(UserLegalAcceptanceRepository::class),
            $this->createStub(GeneralSettingsRepository::class),
            new LegalLanguageResolver(),
        );

        $user = new User()->setStatus(UserStatus::UncompleteProfileInfo);

        self::assertSame([], $service->pendingVersions($user));
    }

    public function testActiveUserIsPendingUntilThePublishedVersionIsAccepted(): void
    {
        $version = new LegalDocumentVersion(new LegalDocument(LegalDocumentType::TermsOfUse), 2);
        $versions = $this->createStub(LegalDocumentVersionRepository::class);
        $versions->method('findPublishedRequiringAcceptance')->willReturn([$version]);

        $acceptances = $this->createStub(UserLegalAcceptanceRepository::class);
        $acceptances->method('hasAcceptance')->willReturn(false);

        $service = new LegalAcceptanceService(
            $this->createStub(EntityManagerInterface::class),
            $versions,
            $acceptances,
            $this->createStub(GeneralSettingsRepository::class),
            new LegalLanguageResolver(),
        );

        $pending = $service->pendingVersions(new User()->setStatus(UserStatus::Active));

        self::assertSame([$version], $pending);
    }
}
