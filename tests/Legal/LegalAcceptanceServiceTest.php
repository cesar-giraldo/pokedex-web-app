<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserStatus;
use App\Entity\GeneralSettings;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Legal\LegalAcceptanceService;
use App\Legal\LegalLanguageResolver;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserLegalAcceptanceRepository;
use DateTime;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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

    public function testDuplicateInsertIsTreatedAsAnExistingAcceptance(): void
    {
        $version = $this->publishedVersion();
        $versions = $this->createStub(LegalDocumentVersionRepository::class);
        $versions->method('findPublishedRequiringAcceptance')->willReturn([$version]);

        $acceptances = $this->createStub(UserLegalAcceptanceRepository::class);
        $acceptances->method('hasAcceptance')->willReturn(false);

        $settings = $this->createStub(GeneralSettingsRepository::class);
        $settings->method('getOrCreateSingleton')->willReturn(new GeneralSettings());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush')->willThrowException(new UniqueConstraintViolationException(
            new class('duplicate key') extends Exception implements DriverException {
                public function getSQLState(): string
                {
                    return '23505';
                }
            },
            null,
        ));

        $service = new LegalAcceptanceService(
            $entityManager,
            $versions,
            $acceptances,
            $settings,
            new LegalLanguageResolver(),
        );

        $acceptance = $service->acceptCurrent(new User()->setStatus(UserStatus::Active), '127.0.0.1', 'phpunit');

        self::assertInstanceOf(UserLegalAcceptance::class, $acceptance);
    }

    private function publishedVersion(): LegalDocumentVersion
    {
        $version = new LegalDocumentVersion(new LegalDocument(LegalDocumentType::PrivacyPolicy), 1);
        $translation = new LegalDocumentVersionTranslation($version, SupportedLanguage::Spanish->value);
        $translation->setTitle('Política de prueba');
        $translation->setContentHtml('<p>Contenido</p>');
        $translation->setAcceptanceLabel('Acepto');
        $version->publish(true, 'Política v1', 'Léela y acéptala.', new DateTime());

        return $version;
    }
}
