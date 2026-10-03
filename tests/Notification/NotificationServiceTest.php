<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Entity\Enum\NotificationType;
use App\Entity\Notification;
use App\Entity\User;
use App\Notification\Exception\NotificationAccessDeniedException;
use App\Notification\NotificationService;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[Group('unit')]
final class NotificationServiceTest extends TestCase
{
    public function testNotifyPersistsOneRowPerDistinctRecipient(): void
    {
        $recipient = $this->userWithId(1);
        $duplicate = $this->userWithId(1);
        $other = $this->userWithId(2);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist')->with(self::isInstanceOf(Notification::class));
        $entityManager->expects(self::once())->method('flush');

        $service = new NotificationService($entityManager, $this->createStub(NotificationRepository::class));
        $created = $service->notify(
            recipients: [$recipient, $duplicate, $other],
            type: NotificationType::UserCreated,
            title: 'Nuevo usuario',
            message: 'Se creó una cuenta.',
            actionUrl: '/admin/users/9',
            actor: $this->userWithId(3),
        );

        self::assertCount(2, $created);
        self::assertSame(NotificationType::UserCreated, $created[0]->getType());
        self::assertSame('/admin/users/9', $created[0]->getActionUrl());
        self::assertFalse($created[0]->isRead());
    }

    public function testNotifyRejectsBlankContentAndSkipsFlushWhenNobodyIsTargeted(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $service = new NotificationService($entityManager, $this->createStub(NotificationRepository::class));

        $this->expectException(InvalidArgumentException::class);
        $service->notify($this->userWithId(1), NotificationType::SystemInfo, '  ', 'mensaje');
    }

    public function testMarkAsReadPersistsOnlyForTheRecipient(): void
    {
        $recipient = $this->userWithId(4);
        $notification = new Notification($recipient, NotificationType::SystemInfo, 'Aviso', 'Detalle');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new NotificationService($entityManager, $this->createStub(NotificationRepository::class));
        $service->markAsRead($notification, $recipient);

        self::assertTrue($notification->isRead());
        self::assertNotNull($notification->getReadAt());
    }

    public function testMarkAsReadRejectsAnotherUser(): void
    {
        $notification = new Notification($this->userWithId(4), NotificationType::SystemInfo, 'Aviso', 'Detalle');
        $service = new NotificationService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(NotificationRepository::class),
        );

        $this->expectException(NotificationAccessDeniedException::class);
        $service->markAsRead($notification, $this->userWithId(5));
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
