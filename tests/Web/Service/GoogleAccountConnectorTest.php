<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Notification\StaffNewUserNotifier;
use App\Repository\GeneralSettingsRepository;
use App\Repository\UserRepository;
use App\Web\Security\GoogleIdentity;
use App\Web\Security\GoogleSignInException;
use App\Web\Service\GoogleAccountConnector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group('unit')]
#[CoversClass(GoogleAccountConnector::class)]
final class GoogleAccountConnectorTest extends TestCase
{
    public function testAVerifiedNewEmailCreatesAnIncompleteProfileWithoutAskingTheTeamTwice(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())->method('findOneByGoogleSub')->with('google-sub')->willReturn(null);
        $users->expects($this->once())->method('findOneByEmail')->with('ada@example.com')->willReturn(null);
        $users->expects($this->once())->method('existsByNickname')->with('ada@example.com')->willReturn(false);

        $created = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->callback(static function (User $user) use (&$created): bool {
            $created = $user;

            return true;
        }));
        $entityManager->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hashPassword')->willReturn('random-hash');

        $notifier = $this->createMock(StaffNewUserNotifier::class);
        $notifier->expects($this->once())->method('notify')->with($this->callback(
            static fn (User $user): bool => 'ada@example.com' === $user->getEmail(),
        ));

        $user = $this->connector($users, $entityManager, $hasher, $notifier)->connect(
            new GoogleIdentity('google-sub', 'ada@example.com', 'Ada', ''),
            SupportedLanguage::Spanish,
        );

        self::assertInstanceOf(User::class, $created);
        self::assertSame($created, $user);
        self::assertSame(UserStatus::UncompleteProfileInfo, $user->getStatus());
        self::assertTrue($user->hasAssignedRole(UserRole::User));
        self::assertSame('ada@example.com', $user->getNickname());
        self::assertSame('google-sub', $user->getGoogleSub());
        self::assertSame('Ada', $user->getName());
        self::assertSame('-', $user->getLastname());
        self::assertSame('random-hash', $user->getPassword());
        self::assertFalse($user->hasChosenPassword());
        self::assertSame(SupportedLanguage::Spanish, $user->getPreferredLanguage());
        self::assertSame(GeneralSettings::DEFAULT_TIMEZONE, $user->getTimezone());
        self::assertSame(GeneralSettings::DEFAULT_LOCALE, $user->getLocale());
        self::assertSame(GeneralSettings::DEFAULT_TIME_FORMAT, $user->getTimeFormat());
        self::assertFalse($user->isHidden());
    }

    public function testAVerifiedEmailLinksTheExistingAccountAndSkipsTheStaffNotice(): void
    {
        $existing = $this->user(UserStatus::UnconfirmedAccount);
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())->method('findOneByGoogleSub')->willReturn(null);
        $users->expects($this->once())->method('findOneByEmail')->with('ada@example.com')->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hashPassword');
        $notifier = $this->createMock(StaffNewUserNotifier::class);
        $notifier->expects($this->never())->method('notify');

        $user = $this->connector($users, $entityManager, $hasher, $notifier)->connect(
            new GoogleIdentity('google-sub', 'ada@example.com', 'Ada', 'Lovelace'),
            SupportedLanguage::Spanish,
        );

        self::assertSame($existing, $user);
        self::assertSame(UserStatus::UncompleteProfileInfo, $user->getStatus());
        self::assertSame('google-sub', $user->getGoogleSub());
        self::assertSame('hash', $user->getPassword());
        self::assertTrue($user->hasChosenPassword());
    }

    public function testAnAccountThatAlreadyHasAnotherGoogleSubjectIsRejected(): void
    {
        $existing = $this->user(UserStatus::Active, 'other-sub');
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())->method('findOneByGoogleSub')->willReturn(null);
        $users->expects($this->once())->method('findOneByEmail')->willReturn($existing);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hashPassword');
        $notifier = $this->createMock(StaffNewUserNotifier::class);
        $notifier->expects($this->never())->method('notify');

        try {
            $this->connector($users, $entityManager, $hasher, $notifier)
                ->connect(new GoogleIdentity('google-sub', 'ada@example.com', 'Ada', 'Lovelace'), SupportedLanguage::Spanish);
            self::fail('A different Google subject must be rejected.');
        } catch (GoogleSignInException) {
            self::assertSame('other-sub', $existing->getGoogleSub());
        }
    }

    public function testABannedAccountIsRejected(): void
    {
        $banned = $this->user(UserStatus::Banned);
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())->method('findOneByGoogleSub')->willReturn(null);
        $users->expects($this->once())->method('findOneByEmail')->willReturn($banned);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hashPassword');
        $notifier = $this->createMock(StaffNewUserNotifier::class);
        $notifier->expects($this->never())->method('notify');

        $this->expectException(GoogleSignInException::class);

        $this->connector($users, $entityManager, $hasher, $notifier)
            ->connect(new GoogleIdentity('google-sub', 'ada@example.com', 'Ada', 'Lovelace'), SupportedLanguage::Spanish);
    }

    /**
     * @param MockObject&UserRepository              $users
     * @param MockObject&EntityManagerInterface      $entityManager
     * @param MockObject&UserPasswordHasherInterface $hasher
     * @param MockObject&StaffNewUserNotifier        $notifier
     */
    private function connector(
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
        StaffNewUserNotifier $notifier,
    ): GoogleAccountConnector {
        $settings = $this->createStub(GeneralSettingsRepository::class);
        $settings->method('findSingleton')->willReturn(null);

        return new GoogleAccountConnector(
            $users,
            $entityManager,
            $hasher,
            new GeneralSettingsProvider($settings),
            $notifier,
        );
    }

    private function user(UserStatus $status, ?string $googleSub = null): User
    {
        return new User()
            ->setName('Ada')
            ->setLastname('Lovelace')
            ->setEmail('ada@example.com')
            ->setNickname('ada@example.com')
            ->setApplicationRoles([UserRole::User])
            ->setStatus($status)
            ->setIsHidden(false)
            ->setGoogleSub($googleSub)
            ->setPassword('hash');
    }
}
