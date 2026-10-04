<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\GeneralSettings;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;
use App\Legal\Exception\LegalPublicationException;
use App\Legal\LegalContentSanitizer;
use App\Legal\LegalDocumentPublisher;
use App\Legal\LegalLanguageResolver;
use App\Notification\NotificationService;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(LegalDocumentPublisher::class)]
#[Group('unit')]
final class LegalDocumentPublisherTest extends TestCase
{
    public function testFirstPublicationMustRequireAcceptance(): void
    {
        $publisher = $this->publisher(releasedCount: 0, expectNotify: false);
        $draft = $this->draft();

        $this->expectException(LegalPublicationException::class);
        $publisher->publish($draft, false, 'Titulo', 'Mensaje', new User());
    }

    public function testMaterialPublicationNotifiesActiveUsers(): void
    {
        $publisher = $this->publisher(releasedCount: 1, expectNotify: true);
        $draft = $this->draft();

        $publisher->publish($draft, true, 'Política v2', 'Debes aceptar', new User());

        self::assertTrue($draft->isPublished());
        self::assertTrue($draft->requiresReacceptance());
        self::assertNotNull($draft->findTranslation('es')?->getContentHash());
    }

    private function publisher(int $releasedCount, bool $expectNotify): LegalDocumentPublisher
    {
        $settings = GeneralSettings::createWithDefaults()->setEnabledLanguages(['es'])->setWebsiteDefaultLanguage('es');
        $settingsRepository = $this->createStub(GeneralSettingsRepository::class);
        $settingsRepository->method('getOrCreateSingleton')->willReturn($settings);

        $versions = $this->createStub(LegalDocumentVersionRepository::class);
        $versions->method('countReleased')->willReturn($releasedCount);
        $versions->method('findPublished')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($expectNotify ? self::once() : self::never())->method('flush');

        $notificationManager = $this->createMock(EntityManagerInterface::class);
        $notificationManager->expects($expectNotify ? self::once() : self::never())->method('flush');
        $notifications = new NotificationService($notificationManager, $this->createStub(NotificationRepository::class));

        $users = $this->createStub(UserRepository::class);
        $users->method('findByStatus')->willReturn([new User()]);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/admin/legal/accept');

        $html = $this->createStub(HtmlSanitizerInterface::class);
        $html->method('sanitize')->willReturnArgument(0);

        return new LegalDocumentPublisher(
            $entityManager,
            $this->createStub(LegalDocumentRepository::class),
            $versions,
            $users,
            $settingsRepository,
            new LegalLanguageResolver(),
            new LegalContentSanitizer($html),
            $notifications,
            $urls,
        );
    }

    private function draft(): LegalDocumentVersion
    {
        $version = new LegalDocumentVersion(new LegalDocument(LegalDocumentType::PrivacyPolicy), 1);
        $translation = new LegalDocumentVersionTranslation($version, 'es');
        $translation->setTitle('Privacidad')->setContentHtml('<p>Texto</p>')->setAcceptanceLabel('Acepto la política');

        return $version;
    }
}
