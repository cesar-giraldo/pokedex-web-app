<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\LegalDocumentVersionStatus;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\LegalDocumentVersion;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Legal\LegalDocumentPublisher;
use App\Notification\NotificationService;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserRepository;
use App\Tests\Web\Support\AcceptsPendingLegalDocumentsTrait;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function array_unique;
use function array_values;
use function bin2hex;
use function random_bytes;
use function sprintf;

#[Group('functional')]
final class NotificationControllerTest extends WebTestCase
{
    use AcceptsPendingLegalDocumentsTrait;

    private const string LOCALE = 'es';

    /** @var list<int> */
    private array $createdNotificationIds = [];

    /** @var list<string> */
    private array $nicknames = [];

    /** @var list<int> */
    private array $publishedVersionIds = [];

    /** @var list<string> */
    private array $notificationTitles = [];

    private ?int $archivedVersionId = null;

    private ?EntityManagerInterface $entityManager = null;

    protected function tearDown(): void
    {
        $hasCleanup = [] !== $this->createdNotificationIds
            || [] !== $this->nicknames
            || [] !== $this->publishedVersionIds
            || [] !== $this->notificationTitles
            || null !== $this->archivedVersionId;

        if ($hasCleanup && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);

            foreach ($this->createdNotificationIds as $notificationId) {
                $notification = $entityManager->find(Notification::class, $notificationId);
                if ($notification instanceof Notification) {
                    $entityManager->remove($notification);
                }
            }

            foreach (array_values(array_unique($this->notificationTitles)) as $title) {
                $notifications = $entityManager->getRepository(Notification::class)->findBy([
                    'type' => NotificationType::LegalVersionPublished,
                    'title' => $title,
                ]);

                foreach ($notifications as $notification) {
                    $entityManager->remove($notification);
                }
            }

            $entityManager->flush();

            foreach (array_values(array_unique($this->publishedVersionIds)) as $versionId) {
                $version = $entityManager->find(LegalDocumentVersion::class, $versionId);

                if (!$version instanceof LegalDocumentVersion) {
                    continue;
                }

                $acceptances = $entityManager->getRepository(UserLegalAcceptance::class)->findBy([
                    'version' => $version,
                ]);

                foreach ($acceptances as $acceptance) {
                    $entityManager->remove($acceptance);
                }

                $entityManager->flush();
                $entityManager->remove($version);
            }

            $entityManager->flush();
            $this->restoreArchivedVersion($entityManager);

            /** @var UserRepository $users */
            $users = static::getContainer()->get(UserRepository::class);
            foreach ($this->nicknames as $nickname) {
                $user = $users->findOneByNickname($nickname);
                if ($user instanceof User) {
                    $entityManager->remove($user);
                }
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testGuestDoesNotSeeTheBellAndCannotOpenTheInbox(): void
    {
        $client = static::createClient();

        $client->request('GET', '/' . self::LOCALE);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-controller="header-notifications"]');

        $client->request('GET', '/' . self::LOCALE . '/notifications');
        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertStringContainsString('/' . self::LOCALE . '/login', $location);
    }

    public function testAccountUserSeesAPurpleDotOnlyWhileNotificationsAreUnread(): void
    {
        $client = static::createClient();
        $user = $this->createAccountUser();
        $this->notify($user, 'Aviso sin leer', '/es/profile');
        $this->login($client, $user);

        $client->request('GET', '/' . self::LOCALE);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-notifications-status="unread"] [data-notification-unread-dot].bg-brand');
        self::assertSelectorTextContains('body', 'Ver todas las notificaciones');

        $readUser = $this->createAccountUser();
        $readNotification = $this->notify($readUser, 'Aviso leído', '/es/profile');
        $readNotification->markAsRead(new DateTime());
        $this->entityManager()->flush();
        $this->login($client, $readUser);

        $client->request('GET', '/' . self::LOCALE);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-notifications-status="read"]');
        self::assertSelectorNotExists('[data-notifications-status="unread"]');
    }

    public function testOpeningANotificationMarksItReadAndFollowsTheStoredUrl(): void
    {
        $client = static::createClient();
        $user = $this->createAccountUser();
        $notification = $this->notify($user, 'Ir al admin', '/admin/home');
        $this->login($client, $user);

        $crawler = $client->request('GET', '/' . self::LOCALE . '/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ir al admin');
        self::assertSelectorTextContains('body', 'No leída');

        $client->request('GET', sprintf('/%s/notifications/%d/open', self::LOCALE, $notification->getId()));
        self::assertResponseStatusCodeSame(405);

        $client->submit($crawler->filter(sprintf('form[action$="/notifications/%d/open"]', $notification->getId()))->form());
        self::assertResponseRedirects('/admin/home');

        $fresh = $this->reloadNotification($notification);
        self::assertNotNull($fresh->getReadAt());
    }

    public function testUserCannotOpenAnotherUsersNotification(): void
    {
        $client = static::createClient();
        $owner = $this->createAccountUser();
        $notification = $this->notify($owner, 'Solo del dueño', '/es/profile');

        $viewer = $this->createAccountUser();
        $this->notify($viewer, 'Aviso propio', '/es/profile');
        $this->login($client, $viewer);

        $crawler = $client->request('GET', '/' . self::LOCALE . '/notifications');
        $token = $crawler->filter('form[action$="/open"] input[name="_token"]')->first()->attr('value');
        self::assertIsString($token);

        $client->request('POST', sprintf('/%s/notifications/%d/open', self::LOCALE, $notification->getId()), [
            '_token' => $token,
        ]);
        self::assertResponseStatusCodeSame(404);

        $fresh = $this->reloadNotification($notification);
        self::assertNull($fresh->getReadAt());
    }

    public function testMarkAllReadClearsOnlyTheCurrentUsersNotifications(): void
    {
        $client = static::createClient();
        $viewer = $this->createAccountUser();
        $own = $this->notify($viewer, 'La mía', '/es/profile');
        $otherUser = $this->createAccountUser();
        $foreign = $this->notify($otherUser, 'La ajena', '/es/profile');
        $this->login($client, $viewer);

        $crawler = $client->request('GET', '/' . self::LOCALE . '/notifications');
        self::assertSelectorExists('[data-notifications-status="unread"]');

        $client->submit($crawler->filter('form[action$="/notifications/read-all"]')->form());
        self::assertResponseRedirects('/' . self::LOCALE . '/notifications');

        self::assertNotNull($this->reloadNotification($own)->getReadAt());
        self::assertNull($this->reloadNotification($foreign)->getReadAt());

        $client->request('GET', '/' . self::LOCALE);
        self::assertSelectorExists('[data-notifications-status="read"]');
    }

    public function testOpeningAnAcceptedLegalNotificationShowsThePublishedVersion(): void
    {
        $client = static::createClient();
        $user = $this->createAccountUser();
        $this->login($client, $user);
        $user = $this->reloadUser($user);

        $version = $this->publishPrivacyPolicy($user);
        $notification = $this->entityManager()->getRepository(Notification::class)->findOneBy([
            'recipient' => $user,
            'title' => $this->notificationTitles[0] ?? '',
        ]);
        self::assertInstanceOf(Notification::class, $notification);
        $notificationId = $notification->getId();
        self::assertNotNull($notificationId);
        $actionUrl = $notification->getActionUrl();
        self::assertSame('/' . self::LOCALE . '/legal/accept', $actionUrl);

        $crawler = $client->request('GET', '/' . self::LOCALE . '/legal/accept');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action$="/open"] input[name="_token"]')->attr('value');
        self::assertIsString($token);

        $openUrl = sprintf('/%s/notifications/%d/open', self::LOCALE, $notificationId);
        $client->request('POST', $openUrl, ['_token' => $token]);
        self::assertResponseRedirects($actionUrl);

        $crawler = $client->followRedirect();
        $form = $crawler->selectButton('Aceptar y continuar')->form();
        $accepted = $form['accepted'];
        self::assertInstanceOf(ChoiceFormField::class, $accepted);
        $accepted->tick();
        $client->submit($form);

        $client->request('POST', $openUrl, ['_token' => $token]);
        self::assertResponseRedirects(sprintf('/legal/privacy_policy/%d?lang=es', $version->getVersionNumber()));

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Política de prueba');
    }

    public function testUnsafeActionUrlsReturnToTheInbox(): void
    {
        $client = static::createClient();
        $user = $this->createAccountUser();
        $external = $this->notify($user, 'Enlace externo', 'https://evil.example/phish');
        $protocolRelative = $this->notify($user, 'Enlace protocol-relative', '//evil.example/phish');
        $this->login($client, $user);

        $crawler = $client->request('GET', '/' . self::LOCALE . '/notifications');
        $client->submit($crawler->filter(sprintf('form[action$="/notifications/%d/open"]', $external->getId()))->form());
        self::assertResponseRedirects('/' . self::LOCALE . '/notifications');
        self::assertNotNull($this->reloadNotification($external)->getReadAt());

        $crawler = $client->request('GET', '/' . self::LOCALE . '/notifications');
        $client->submit($crawler->filter(sprintf('form[action$="/notifications/%d/open"]', $protocolRelative->getId()))->form());
        self::assertResponseRedirects('/' . self::LOCALE . '/notifications');
        self::assertNotNull($this->reloadNotification($protocolRelative)->getReadAt());
    }

    private function createAccountUser(): User
    {
        $nickname = 'webnote' . bin2hex(random_bytes(4));
        $entityManager = $this->entityManager();
        $user = new User()
            ->setName('Ada')
            ->setLastname('Tester')
            ->setNickname($nickname)
            ->setEmail($nickname . '@example.com')
            ->setApplicationRoles([UserRole::User])
            ->setStatus(UserStatus::Active);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));
        $entityManager->persist($user);
        $entityManager->flush();
        $this->nicknames[] = $user->getNickname();

        return $user;
    }

    private function login(KernelBrowser $client, User $user): void
    {
        $this->acceptPendingLegalDocuments($user);
        $nickname = $user->getNickname();
        $entityManager = $this->entityManager();
        $entityManager->clear();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $reloaded = $users->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $reloaded);
        $client->loginUser($reloaded, 'public');
    }

    private function notify(User $recipient, string $title, ?string $actionUrl): Notification
    {
        /** @var NotificationService $notificationService */
        $notificationService = static::getContainer()->get(NotificationService::class);
        $created = $notificationService->notify(
            recipients: $recipient,
            type: NotificationType::SystemInfo,
            title: $title,
            message: 'Detalle de la notificación de prueba.',
            actionUrl: $actionUrl,
        );

        $notification = $created[0];
        $notificationId = $notification->getId();
        self::assertNotNull($notificationId);
        $this->createdNotificationIds[] = $notificationId;
        $this->entityManager = $this->entityManager();

        return $notification;
    }

    private function publishPrivacyPolicy(User $actor): LegalDocumentVersion
    {
        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $document = $documents->findOneByType(LegalDocumentType::PrivacyPolicy);
        self::assertNotNull($document);

        /** @var LegalDocumentVersionRepository $versions */
        $versions = static::getContainer()->get(LegalDocumentVersionRepository::class);
        $current = $versions->findPublished($document);
        $this->archivedVersionId = $current?->getId();

        /** @var GeneralSettingsRepository $settingsRepository */
        $settingsRepository = static::getContainer()->get(GeneralSettingsRepository::class);
        $languages = $settingsRepository->getOrCreateSingleton()->getEnabledLanguages();

        $draft = $publisher->startDraft($document, $actor);
        $versionId = $draft->getId();
        self::assertNotNull($versionId);
        $this->publishedVersionIds[] = $versionId;

        $payload = [];
        foreach ($languages as $language) {
            $payload[$language] = [
                'title' => 'Política de prueba',
                'contentHtml' => '<p>Contenido de prueba</p>',
                'acceptanceLabel' => 'Acepto la política de prueba',
                'summary' => 'Versión de prueba',
            ];
        }

        $publisher->updateDraft($draft, $payload);
        $title = 'Política de prueba ' . $draft->getVersionNumber() . ' ' . bin2hex(random_bytes(4));
        $this->notificationTitles[] = $title;
        $publisher->publish($draft, true, $title, 'Debes aceptar el documento de prueba.', $actor);

        return $draft;
    }

    private function reloadUser(User $user): User
    {
        $nickname = $user->getNickname();
        $this->entityManager()->clear();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $reloaded = $users->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $reloaded);

        return $reloaded;
    }

    private function restoreArchivedVersion(EntityManagerInterface $entityManager): void
    {
        if (null === $this->archivedVersionId) {
            return;
        }

        $version = $entityManager->find(LegalDocumentVersion::class, $this->archivedVersionId);

        if (!$version instanceof LegalDocumentVersion || $version->isPublished()) {
            return;
        }

        $status = new ReflectionProperty(LegalDocumentVersion::class, 'status');
        $status->setValue($version, LegalDocumentVersionStatus::Published);
        $entityManager->flush();
    }

    private function reloadNotification(Notification $notification): Notification
    {
        $notificationId = $notification->getId();
        self::assertNotNull($notificationId);
        $this->entityManager()->clear();
        $fresh = $this->entityManager()->find(Notification::class, $notificationId);
        self::assertInstanceOf(Notification::class, $fresh);

        return $fresh;
    }

    private function entityManager(): EntityManagerInterface
    {
        if (null === $this->entityManager || !$this->entityManager->isOpen()) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $this->entityManager = $entityManager;
        }

        return $this->entityManager;
    }
}
