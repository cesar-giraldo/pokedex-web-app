<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\NotificationType;
use App\Entity\Notification;
use App\Entity\User;
use App\Notification\NotificationService;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function sprintf;

#[Group('functional')]
final class NotificationControllerTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    /** @var list<int> */
    private array $createdNotificationIds = [];

    private ?EntityManagerInterface $entityManager = null;

    protected function tearDown(): void
    {
        if (null !== $this->entityManager) {
            foreach ($this->createdNotificationIds as $notificationId) {
                $notification = $this->entityManager->find(Notification::class, $notificationId);
                if ($notification instanceof Notification) {
                    $this->entityManager->remove($notification);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testInboxListsUnreadNotificationAndOpeningItMarksItRead(): void
    {
        $client = static::createClient();
        $admin = $this->loginAsAdmin($client);
        $notification = $this->notify($admin, 'Alta de usuario', '/admin/home');

        $client->request('GET', '/admin/home');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Alta de usuario');
        self::assertSelectorExists('[data-notifications-status="unread"]');

        $crawler = $client->request('GET', '/admin/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Alta de usuario');
        self::assertSelectorTextContains('body', 'No leída');

        $client->request('GET', sprintf('/admin/notifications/%d/open', $notification->getId()));
        self::assertResponseStatusCodeSame(405);

        $client->submit($crawler->filter(sprintf('form[action$="/notifications/%d/open"]', $notification->getId()))->form());
        self::assertResponseRedirects('/admin/home');

        $client->request('GET', '/admin/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'No leída');
    }

    public function testMarkAllReadClearsTheUnreadBadge(): void
    {
        $client = static::createClient();
        $admin = $this->loginAsAdmin($client);
        $this->notify($admin, 'Aviso de sistema', null);

        $crawler = $client->request('GET', '/admin/notifications');
        self::assertSelectorExists('[data-notifications-status="unread"]');

        $client->submit($crawler->filter('form[action$="/notifications/read-all"]')->form());
        self::assertResponseRedirects('/admin/notifications');

        $client->request('GET', '/admin/home');
        self::assertSelectorExists('[data-notifications-status="read"]');
        self::assertSelectorTextContains('body', 'Aviso de sistema');
    }

    public function testUserCannotOpenAnotherUsersNotification(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $notification = $this->notify($developer, 'Solo developer', '/admin/home');

        $admin = $this->loginAsAdmin($client);
        $this->notify($admin, 'Aviso propio', '/admin/home');
        $crawler = $client->request('GET', '/admin/notifications');
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        self::assertIsString($token);

        $client->request('POST', sprintf('/admin/notifications/%d/open', $notification->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(404);
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

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        return $notification;
    }
}
