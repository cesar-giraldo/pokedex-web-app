<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Entity\Enum\NotificationType;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Notification;
use App\Entity\User;
use App\Notification\NotificationService;
use App\Repository\UserRepository;
use App\Tests\Web\Support\AcceptsPendingLegalDocumentsTrait;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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

    private ?EntityManagerInterface $entityManager = null;

    protected function tearDown(): void
    {
        if (([] !== $this->createdNotificationIds || [] !== $this->nicknames) && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            foreach ($this->createdNotificationIds as $notificationId) {
                $notification = $entityManager->find(Notification::class, $notificationId);
                if ($notification instanceof Notification) {
                    $entityManager->remove($notification);
                }
            }

            $entityManager->flush();

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
