<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Entity\AccountConfirmationCode;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Notification\Email\AccountConfirmationCodeSender;
use App\Notification\StaffNewUserNotifier;
use App\Repository\AccountConfirmationCodeRepository;
use App\Web\Registration\ConfirmationAttempt;
use App\Web\Security\ConfirmationCodeHasher;
use App\Web\Service\AccountConfirmationService;
use App\Web\Service\ResendQuota;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AccountConfirmationService::class)]
#[Group('unit')]
final class AccountConfirmationServiceTest extends TestCase
{
    private ConfirmationCodeHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new ConfirmationCodeHasher('test');
    }

    public function testIssuedCodeIsSixDigitsAndOnlyItsHashIsStored(): void
    {
        $user = $this->user();
        $repository = $this->createMock(AccountConfirmationCodeRepository::class);
        $repository->method('findUnconsumed')->willReturn([]);
        $stored = null;
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $code) use (&$stored): void {
                $stored = $code;
            },
        );
        $service = $this->service($repository, $manager);

        $plainCode = $service->issue($user);

        self::assertMatchesRegularExpression('/^\d{6}$/', $plainCode);
        self::assertInstanceOf(AccountConfirmationCode::class, $stored);
        self::assertNotSame($plainCode, $stored->getCodeHash());
        self::assertTrue($this->hasher->verify($plainCode, $stored->getCodeHash()));
    }

    public function testAMatchingCodeConfirmsTheAccountOnce(): void
    {
        $user = $this->user();
        $plainCode = '123456';
        $code = new AccountConfirmationCode($user, $this->hasher->hash($plainCode), new DateTime('+2 hours'));
        $repository = $this->createMock(AccountConfirmationCodeRepository::class);
        $repository->method('findUnconsumed')->willReturnOnConsecutiveCalls([$code], []);
        $notifier = $this->createMock(StaffNewUserNotifier::class);
        $notifier->expects(self::once())->method('notify')->with($user);
        $service = $this->service($repository, $this->manager(), notifier: $notifier);

        self::assertSame(ConfirmationAttempt::Confirmed, $service->verify($user, $plainCode));
        self::assertSame(UserStatus::UncompleteProfileInfo, $user->getStatus());
        self::assertTrue($code->isConsumed());
        self::assertSame(ConfirmationAttempt::Missing, $service->verify($user, $plainCode));
    }

    public function testAWrongCodeDoesNotConfirmAndFiveFailuresInvalidateIt(): void
    {
        $user = $this->user();
        $code = new AccountConfirmationCode($user, $this->hasher->hash('123456'), new DateTime('+2 hours'));
        $repository = $this->createMock(AccountConfirmationCodeRepository::class);
        $repository->method('findUnconsumed')->willReturn([$code]);
        $notifier = $this->createMock(StaffNewUserNotifier::class);
        $notifier->expects(self::never())->method('notify');
        $service = $this->service($repository, $this->manager(), notifier: $notifier);

        self::assertSame(ConfirmationAttempt::Mismatch, $service->verify($user, '000000'));
        self::assertSame(1, $code->getAttempts());
        self::assertFalse($code->isConsumed());

        $code->registerFailedAttempt();
        $code->registerFailedAttempt();
        $code->registerFailedAttempt();

        self::assertSame(ConfirmationAttempt::Exhausted, $service->verify($user, '000000'));
        self::assertTrue($code->isConsumed());
        self::assertSame(UserStatus::UnconfirmedAccount, $user->getStatus());
    }

    public function testAnExpiredCodeCannotBeUsed(): void
    {
        $user = $this->user();
        $code = new AccountConfirmationCode($user, $this->hasher->hash('123456'), new DateTime('-1 minute'));
        $repository = $this->createMock(AccountConfirmationCodeRepository::class);
        $repository->method('findUnconsumed')->willReturn([$code]);
        $service = $this->service($repository, $this->manager());

        self::assertSame(ConfirmationAttempt::Expired, $service->verify($user, '123456'));
        self::assertTrue($code->isConsumed());
        self::assertSame(UserStatus::UnconfirmedAccount, $user->getStatus());
    }

    public function testResendConsumesThePreviousCode(): void
    {
        $user = $this->user();
        $previous = new AccountConfirmationCode(
            $user,
            $this->hasher->hash('111111'),
            new DateTime('+2 hours'),
            new DateTime('-2 minutes'),
        );
        $repository = $this->createMock(AccountConfirmationCodeRepository::class);
        $repository->method('findLatest')->willReturn($previous);
        $repository->method('findUnconsumed')->willReturn([$previous]);
        $sender = $this->createMock(AccountConfirmationCodeSender::class);
        $sender->expects(self::once())->method('send');
        $quota = $this->createMock(ResendQuota::class);
        $quota->method('allow')->willReturn(true);
        $service = $this->service($repository, $this->manager(), $sender, quota: $quota, environment: 'prod');

        self::assertSame(ConfirmationAttempt::Resent, $service->resend($user));
        self::assertTrue($previous->isConsumed());
    }

    public function testResendWaitsForTheCooldown(): void
    {
        $user = $this->user();
        $latest = new AccountConfirmationCode($user, $this->hasher->hash('111111'), new DateTime('+2 hours'));
        $repository = $this->createMock(AccountConfirmationCodeRepository::class);
        $repository->method('findLatest')->willReturn($latest);
        $sender = $this->createMock(AccountConfirmationCodeSender::class);
        $sender->expects(self::never())->method('send');
        $quota = $this->createMock(ResendQuota::class);
        $quota->expects(self::never())->method('allow');
        $service = $this->service($repository, $this->manager(), $sender, quota: $quota, environment: 'prod');

        self::assertSame(ConfirmationAttempt::Cooldown, $service->resend($user));
        self::assertFalse($latest->isConsumed());
    }

    public function testResendStopsAtTheQuota(): void
    {
        $user = $this->user();
        $repository = $this->createMock(AccountConfirmationCodeRepository::class);
        $repository->method('findLatest')->willReturn(null);
        $quota = $this->createMock(ResendQuota::class);
        $quota->method('allow')->willReturn(false);
        $sender = $this->createMock(AccountConfirmationCodeSender::class);
        $sender->expects(self::never())->method('send');
        $service = $this->service($repository, $this->manager(), $sender, quota: $quota);

        self::assertSame(ConfirmationAttempt::Limited, $service->resend($user));
    }

    public function testAMailFailureKeepsTheNewCode(): void
    {
        $user = $this->user();
        $repository = $this->createMock(AccountConfirmationCodeRepository::class);
        $repository->method('findLatest')->willReturn(null);
        $repository->method('findUnconsumed')->willReturn([]);
        $sender = $this->createMock(AccountConfirmationCodeSender::class);
        $sender->method('send')->willThrowException(new RuntimeException('smtp'));
        $quota = $this->createMock(ResendQuota::class);
        $quota->method('allow')->willReturn(true);
        $manager = $this->manager();
        $manager->expects(self::once())->method('flush');
        $service = $this->service($repository, $manager, $sender, quota: $quota);

        self::assertSame(ConfirmationAttempt::MailFailed, $service->resend($user));
    }

    private function service(
        AccountConfirmationCodeRepository $repository,
        EntityManagerInterface $manager,
        ?AccountConfirmationCodeSender $sender = null,
        ?StaffNewUserNotifier $notifier = null,
        ?ResendQuota $quota = null,
        string $environment = 'test',
    ): AccountConfirmationService {
        $sender ??= $this->createMock(AccountConfirmationCodeSender::class);
        $notifier ??= $this->createMock(StaffNewUserNotifier::class);
        $quota ??= $this->createStub(ResendQuota::class);

        return new AccountConfirmationService(
            $repository,
            $manager,
            $this->hasher,
            $sender,
            $notifier,
            $quota,
            $environment,
        );
    }

    /**
     * @return EntityManagerInterface&MockObject
     */
    private function manager(): EntityManagerInterface
    {
        return $this->createMock(EntityManagerInterface::class);
    }

    private function user(): User
    {
        return new User()
            ->setName('Ada')
            ->setLastname('Lovelace')
            ->setEmail('ada@example.com')
            ->setNickname('ada@example.com')
            ->setPassword('hashed')
            ->setApplicationRoles([UserRole::User])
            ->setStatus(UserStatus::UnconfirmedAccount);
    }
}
