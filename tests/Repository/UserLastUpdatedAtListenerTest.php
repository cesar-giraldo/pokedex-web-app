<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

#[Group('integration')]
final class UserLastUpdatedAtListenerTest extends KernelTestCase
{
    /** @var list<int> */
    private array $createdUserIds = [];

    private ?EntityManagerInterface $entityManager = null;

    protected function tearDown(): void
    {
        if (null !== $this->entityManager) {
            foreach ($this->createdUserIds as $userId) {
                $user = $this->entityManager->find(User::class, $userId);
                if ($user instanceof User) {
                    $this->entityManager->remove($user);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testLoginTrackingDoesNotChangeLastUpdatedAt(): void
    {
        $user = $this->createPersistedUser();
        $frozen = $this->freezeLastUpdatedAt($user);

        $user->recordSuccessfulInteractiveLogin('127.0.0.1');
        $this->entityManager?->flush();
        $this->entityManager?->refresh($user);

        self::assertSame(
            $frozen->format('Y-m-d H:i:s'),
            $user->getLastUpdatedAt()->format('Y-m-d H:i:s'),
        );
        self::assertNotNull($user->getLastLoginAt());
    }

    public function testFailedLoginTrackingDoesNotChangeLastUpdatedAt(): void
    {
        $user = $this->createPersistedUser();
        $frozen = $this->freezeLastUpdatedAt($user);

        $user->recordFailedLoginAttempt();
        $this->entityManager?->flush();
        $this->entityManager?->refresh($user);

        self::assertSame(
            $frozen->format('Y-m-d H:i:s'),
            $user->getLastUpdatedAt()->format('Y-m-d H:i:s'),
        );
        self::assertNotNull($user->getLastFailedLoginAt());
    }

    public function testPasswordChangeDoesNotChangeLastUpdatedAt(): void
    {
        $user = $this->createPersistedUser();
        $frozen = $this->freezeLastUpdatedAt($user);

        $user->setPassword('new-hash');
        $user->setPasswordUpdatedAt(new DateTime());
        $this->entityManager?->flush();
        $this->entityManager?->refresh($user);

        self::assertSame(
            $frozen->format('Y-m-d H:i:s'),
            $user->getLastUpdatedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testProfileDataChangeUpdatesLastUpdatedAt(): void
    {
        $user = $this->createPersistedUser();
        $frozen = $this->freezeLastUpdatedAt($user);

        $user->setName('Updated');
        $this->entityManager?->flush();
        $this->entityManager?->refresh($user);

        self::assertNotSame(
            $frozen->format('Y-m-d H:i:s'),
            $user->getLastUpdatedAt()->format('Y-m-d H:i:s'),
        );
    }

    private function createPersistedUser(): User
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $user = new User()
            ->setName('Stamp')
            ->setLastname('User')
            ->setEmail(sprintf('stamp-%s@example.com', $suffix))
            ->setNickname('stamp' . $suffix)
            ->setCountryCode(57)
            ->setCellphone('3019' . sprintf('%06d', random_int(0, 999999)))
            ->setApplicationRoles([UserRole::Operator])
            ->setStatus(UserStatus::Active)
            ->setIsHidden(true);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        $entityManager->persist($user);
        $entityManager->flush();

        $userId = $user->getId();
        self::assertNotNull($userId);
        $this->createdUserIds[] = $userId;

        return $user;
    }

    private function freezeLastUpdatedAt(User $user): DateTime
    {
        $frozen = new DateTime('2020-01-15 08:30:00');
        $user->setLastUpdatedAt($frozen);
        $this->entityManager?->flush();
        $this->entityManager?->refresh($user);

        self::assertSame(
            $frozen->format('Y-m-d H:i:s'),
            $user->getLastUpdatedAt()->format('Y-m-d H:i:s'),
        );

        return $frozen;
    }
}
