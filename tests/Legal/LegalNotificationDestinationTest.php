<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\Notification;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Legal\LegalNotificationDestination;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserLegalAcceptanceRepository;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(LegalNotificationDestination::class)]
#[Group('unit')]
final class LegalNotificationDestinationTest extends TestCase
{
    public function testAcceptedVersionPointsToThePublicPageInTheUserLanguage(): void
    {
        $version = $this->publishedVersion(LegalDocumentType::PrivacyPolicy, 2);
        $user = new User();
        $notification = $this->legalNotification($user, [
            'documentType' => LegalDocumentType::PrivacyPolicy->value,
            'versionNumber' => 2,
        ]);

        $destination = $this->destination($version, accepted: true, publicUrl: '/legal/privacy_policy/2?lang=es');

        self::assertSame('/legal/privacy_policy/2?lang=es', $destination->urlForAcceptedVersion($user, $notification));
    }

    public function testPendingVersionKeepsTheNotificationAction(): void
    {
        $version = $this->publishedVersion(LegalDocumentType::PrivacyPolicy, 1);
        $user = new User();
        $notification = $this->legalNotification($user, [
            'documentType' => LegalDocumentType::PrivacyPolicy->value,
            'versionNumber' => '1',
        ]);

        $destination = $this->destination($version, accepted: false, publicUrl: '/legal/privacy_policy/1?lang=es');

        self::assertNull($destination->urlForAcceptedVersion($user, $notification));
    }

    public function testMissingPayloadOrVersionKeepsTheNotificationAction(): void
    {
        $user = new User();
        $withoutPayload = $this->legalNotification($user, null);
        $missingVersion = $this->legalNotification($user, [
            'documentType' => LegalDocumentType::TermsOfUse->value,
            'versionNumber' => 9,
        ]);

        $destination = $this->destination(null, accepted: true, publicUrl: '/legal/terms_of_use/9?lang=es');

        self::assertNull($destination->urlForAcceptedVersion($user, $withoutPayload));
        self::assertNull($destination->urlForAcceptedVersion($user, $missingVersion));
    }

    public function testDraftVersionIsNotPublic(): void
    {
        $document = new LegalDocument(LegalDocumentType::PrivacyPolicy);
        $version = new LegalDocumentVersion($document, 3);
        $user = new User();
        $notification = $this->legalNotification($user, [
            'documentType' => LegalDocumentType::PrivacyPolicy->value,
            'versionNumber' => 3,
        ]);

        $destination = $this->destination($version, accepted: true, publicUrl: '/legal/privacy_policy/3?lang=es');

        self::assertNull($destination->urlForAcceptedVersion($user, $notification));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function legalNotification(User $user, ?array $payload): Notification
    {
        return new Notification($user, NotificationType::LegalVersionPublished, 'Política v1', 'Léela y acéptala.')
            ->setActionUrl('/admin/legal/accept')
            ->setPayload($payload);
    }

    private function publishedVersion(LegalDocumentType $type, int $versionNumber): LegalDocumentVersion
    {
        $version = new LegalDocumentVersion(new LegalDocument($type), $versionNumber);
        $translation = new LegalDocumentVersionTranslation($version, SupportedLanguage::Spanish->value);
        $translation->setTitle('Política de prueba');
        $translation->setContentHtml('<p>Contenido</p>');
        $translation->setAcceptanceLabel('Acepto');
        $version->publish(true, 'Política v' . $versionNumber, 'Léela y acéptala.', new DateTime());

        return $version;
    }

    private function destination(?LegalDocumentVersion $version, bool $accepted, string $publicUrl): LegalNotificationDestination
    {
        $documents = $this->createStub(LegalDocumentRepository::class);
        $documents->method('findOneByType')->willReturnCallback(
            static fn (LegalDocumentType $type): LegalDocument => $version?->getDocument() ?? new LegalDocument($type),
        );

        $versions = $this->createStub(LegalDocumentVersionRepository::class);
        $versions->method('findOneByDocumentAndNumber')->willReturn($version);

        $acceptances = $this->createStub(UserLegalAcceptanceRepository::class);
        $acceptances->method('hasAcceptance')->willReturn($accepted);

        $settings = new GeneralSettings();
        $settings->setEnabledLanguages([SupportedLanguage::Spanish->value]);
        $settings->setWebsiteDefaultLanguage(SupportedLanguage::Spanish->value);

        $settingsRepository = $this->createStub(GeneralSettingsRepository::class);
        $settingsRepository->method('getOrCreateSingleton')->willReturn($settings);

        $urls = $this->createMock(UrlGeneratorInterface::class);

        if ($accepted && $version instanceof LegalDocumentVersion && !$version->isDraft()) {
            $urls->expects($this->once())
                ->method('generate')
                ->with('app_legal_version', [
                    'type' => $version->getDocument()->getType()->value,
                    'versionNumber' => $version->getVersionNumber(),
                    'lang' => SupportedLanguage::Spanish->value,
                ])
                ->willReturn($publicUrl);
        } else {
            $urls->expects($this->never())->method('generate');
        }

        return new LegalNotificationDestination(
            $documents,
            $versions,
            $acceptances,
            $settingsRepository,
            new LegalLanguageResolver(),
            $urls,
        );
    }
}
