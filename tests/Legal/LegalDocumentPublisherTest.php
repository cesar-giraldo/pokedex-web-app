<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\GeneralSettings;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\Notification;
use App\Entity\User;
use App\Legal\Exception\LegalPublicationException;
use App\Legal\LegalAcceptanceLink;
use App\Legal\LegalContentSanitizer;
use App\Legal\LegalDocumentPublisher;
use App\Legal\LegalLanguageResolver;
use App\Notification\NotificationService;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use ArrayObject;
use DateTime;
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

    public function testPublicationUsesTheWebUrlForUserRoleAndTheAdminUrlForStaff(): void
    {
        $account = new User()
            ->setApplicationRoles([UserRole::User])
            ->setPreferredLanguage(SupportedLanguage::English);
        $staff = new User()->setApplicationRoles([UserRole::Operator]);
        $captured = new ArrayObject();

        $publisher = $this->publisher(
            releasedCount: 1,
            expectNotify: true,
            recipients: [$account, $staff],
            settings: GeneralSettings::createWithDefaults()->setEnabledLanguages(['es', 'en'])->setWebsiteDefaultLanguage('es'),
            urls: $this->acceptanceUrls(),
            captured: $captured,
        );

        $publisher->publish($this->draftWithEnglish(), true, 'Política v2', 'Debes aceptar', new User());

        $urls = [];
        $titles = [];
        $messages = [];

        foreach ($captured as $notification) {
            self::assertInstanceOf(Notification::class, $notification);
            $urls[] = $notification->getActionUrl();
            $titles[] = $notification->getTitle();
            $messages[] = $notification->getMessage();
        }

        self::assertSame(['/en/legal/accept', '/admin/legal/accept'], $urls);
        self::assertSame(['Política v2', 'Política v2'], $titles);
        self::assertSame(['Debes aceptar', 'Debes aceptar'], $messages);
    }

    public function testDeleteDraftRemovesTheVersion(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove');
        $entityManager->expects(self::once())->method('flush');

        $this->publisherWithEntityManager($entityManager)->deleteDraft($this->draft());
    }

    public function testDeleteDraftRejectsAPublishedVersion(): void
    {
        $version = $this->draft();
        $version->publish(true, 'Título', 'Mensaje', new DateTime());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');

        $this->expectException(LegalPublicationException::class);
        $this->publisherWithEntityManager($entityManager)->deleteDraft($version);
    }

    /**
     * @param list<User>|null                     $recipients
     * @param ArrayObject<int, Notification>|null $captured
     */
    private function publisher(
        int $releasedCount,
        bool $expectNotify,
        ?array $recipients = null,
        ?GeneralSettings $settings = null,
        ?UrlGeneratorInterface $urls = null,
        ?ArrayObject $captured = null,
    ): LegalDocumentPublisher {
        $settings ??= GeneralSettings::createWithDefaults()->setEnabledLanguages(['es'])->setWebsiteDefaultLanguage('es');
        $settingsRepository = $this->createStub(GeneralSettingsRepository::class);
        $settingsRepository->method('getOrCreateSingleton')->willReturn($settings);

        $versions = $this->createStub(LegalDocumentVersionRepository::class);
        $versions->method('countReleased')->willReturn($releasedCount);
        $versions->method('findPublished')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($expectNotify ? self::once() : self::never())->method('flush');

        $notificationManager = $this->createMock(EntityManagerInterface::class);
        $notificationManager->expects($expectNotify ? self::once() : self::never())->method('flush');

        if ($captured instanceof ArrayObject) {
            $notificationManager->method('persist')->willReturnCallback(static function (object $entity) use ($captured): void {
                if ($entity instanceof Notification) {
                    $captured->append($entity);
                }
            });
        }

        $notifications = new NotificationService($notificationManager, $this->createStub(NotificationRepository::class));

        $users = $this->createStub(UserRepository::class);
        $users->method('findByStatus')->willReturn($recipients ?? [new User()]);

        if (!$urls instanceof UrlGeneratorInterface) {
            $urls = $this->createStub(UrlGeneratorInterface::class);
            $urls->method('generate')->willReturn('/admin/legal/accept');
        }

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
            new LegalAcceptanceLink($urls, new LegalLanguageResolver(), $settingsRepository),
        );
    }

    private function publisherWithEntityManager(EntityManagerInterface $entityManager): LegalDocumentPublisher
    {
        $html = $this->createStub(HtmlSanitizerInterface::class);
        $settings = $this->createStub(GeneralSettingsRepository::class);
        $urls = $this->createStub(UrlGeneratorInterface::class);

        return new LegalDocumentPublisher(
            $entityManager,
            $this->createStub(LegalDocumentRepository::class),
            $this->createStub(LegalDocumentVersionRepository::class),
            $this->createStub(UserRepository::class),
            $settings,
            new LegalLanguageResolver(),
            new LegalContentSanitizer($html),
            new NotificationService($this->createStub(EntityManagerInterface::class), $this->createStub(NotificationRepository::class)),
            new LegalAcceptanceLink($urls, new LegalLanguageResolver(), $settings),
        );
    }

    private function acceptanceUrls(): UrlGeneratorInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $route, array $parameters = []): string {
            if ('app_public_legal_accept' === $route) {
                return '/' . ($parameters['_locale'] ?? 'es') . '/legal/accept';
            }

            return '/admin/legal/accept';
        });

        return $urls;
    }

    private function draft(): LegalDocumentVersion
    {
        $version = new LegalDocumentVersion(new LegalDocument(LegalDocumentType::PrivacyPolicy), 1);
        $translation = new LegalDocumentVersionTranslation($version, 'es');
        $translation->setTitle('Privacidad')->setContentHtml('<p>Texto</p>')->setAcceptanceLabel('Acepto la política');

        return $version;
    }

    private function draftWithEnglish(): LegalDocumentVersion
    {
        $version = $this->draft();
        $translation = new LegalDocumentVersionTranslation($version, 'en');
        $translation->setTitle('Privacy')->setContentHtml('<p>Text</p>')->setAcceptanceLabel('I accept');

        return $version;
    }
}
