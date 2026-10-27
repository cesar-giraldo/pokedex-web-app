<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Enum\PasswordResetChannel;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Notification\Email\PasswordResetCompletedSender;
use App\Notification\Email\PasswordResetRequestSender;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Security\PasswordResetService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function explode;
use function hash_hmac;

#[CoversClass(PasswordResetService::class)]
#[Group('unit')]
final class PasswordResetServiceTest extends TestCase
{
    private const string SECRET = 'test-secret';

    public function testEmailOrNicknameResolvesOneAccountAndStoresOnlyTheVerifierHash(): void
    {
        $user = $this->user();
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('findOneByEmail')->with('ada@example.com')->willReturn($user);
        $users->expects(self::once())->method('findOneByNickname')->with('ada@example.com')->willReturn(null);
        $stored = new PasswordResetTokenBox();
        $previous = new PasswordResetToken(
            $user,
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            hash_hmac('sha256', 'old', self::SECRET),
            PasswordResetChannel::Web,
            new DateTime('+1 hour'),
        );
        $tokens = $this->createStub(PasswordResetTokenRepository::class);
        $tokens->method('findUnconsumed')->willReturn([$previous]);
        $tokens->method('findOneBySelector')->willReturnCallback(
            static function (string $selector) use ($stored): ?PasswordResetToken {
                $token = $stored->token;

                return $token instanceof PasswordResetToken && $token->getSelector() === $selector ? $token : null;
            },
        );
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use ($stored): void {
                if ($entity instanceof PasswordResetToken) {
                    $stored->token = $entity;
                }
            },
        );
        $publicToken = null;
        $requestSender = $this->createMock(PasswordResetRequestSender::class);
        $requestSender->expects(self::once())->method('send')->willReturnCallback(
            static function (User $sentUser, string $token, PasswordResetChannel $channel, SupportedLanguage $language) use (&$publicToken, $user): void {
                $publicToken = $token;
                self::assertSame($user, $sentUser);
                self::assertSame(PasswordResetChannel::Web, $channel);
                self::assertSame(SupportedLanguage::English, $language);
            },
        );
        $service = $this->service($users, $tokens, $manager, $requestSender, $this->createStub(PasswordResetCompletedSender::class));

        $result = $service->request('  Ada@Example.com ', PasswordResetChannel::Web, SupportedLanguage::English, '127.0.0.1');

        self::assertFalse($result->rateLimitedByIp);
        self::assertTrue($previous->isConsumed());
        $issued = $stored->token;
        self::assertInstanceOf(PasswordResetToken::class, $issued);
        self::assertIsString($publicToken);
        $verifier = explode('.', $publicToken)[1];
        self::assertNotSame($verifier, $issued->getVerifierHash());
        self::assertSame(hash_hmac('sha256', $verifier, self::SECRET), $issued->getVerifierHash());
        self::assertEqualsWithDelta(PasswordResetToken::TTL_HOURS * 3600, $issued->getExpiresAt()->getTimestamp() - $issued->getCreatedAt()->getTimestamp(), 5);
        self::assertSame($issued, $service->findOpen($publicToken, PasswordResetChannel::Web));
        self::assertNull($service->findOpen($publicToken, PasswordResetChannel::Admin));
    }

    public function testNicknameWithoutAtSignSkipsTheEmailLookup(): void
    {
        $user = $this->user(nickname: 'ada-nick');
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::never())->method('findOneByEmail');
        $users->expects(self::once())->method('findOneByNickname')->with('ada-nick')->willReturn($user);
        $requestSender = $this->createMock(PasswordResetRequestSender::class);
        $requestSender->expects(self::once())->method('send');
        $service = $this->service($users, $this->tokens(), $this->manager(), $requestSender, $this->createStub(PasswordResetCompletedSender::class));

        $result = $service->request('Ada-Nick', PasswordResetChannel::Web, SupportedLanguage::Spanish, '10.0.0.2');

        self::assertFalse($result->rateLimitedByIp);
    }

    public function testUnknownBannedInactiveMissingEmailWrongPortalAndAmbiguousMatchesDoNotSendMail(): void
    {
        $banned = $this->user(status: UserStatus::Banned, id: 2);
        $inactive = $this->user(status: UserStatus::Inactive, id: 3);
        $withoutEmail = $this->user(email: null, id: 4);
        $webOnly = $this->user(roles: [UserRole::User], id: 5);
        $staffOnly = $this->user(roles: [UserRole::Operator], id: 6);
        $first = $this->user(id: 7);
        $second = $this->user(id: 8);
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturnOnConsecutiveCalls(null, $banned, $inactive, $withoutEmail, $webOnly, $staffOnly, $first);
        $users->method('findOneByNickname')->willReturnOnConsecutiveCalls(null, null, null, null, null, null, $second);
        $requestSender = $this->createMock(PasswordResetRequestSender::class);
        $requestSender->expects(self::never())->method('send');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('persist');
        $service = $this->service($users, $this->tokens(), $manager, $requestSender, $this->createStub(PasswordResetCompletedSender::class));

        $identifiers = [
            ['missing@example.com', PasswordResetChannel::Web],
            ['banned@example.com', PasswordResetChannel::Web],
            ['inactive@example.com', PasswordResetChannel::Web],
            ['no-mail@example.com', PasswordResetChannel::Web],
            ['web-only@example.com', PasswordResetChannel::Admin],
            ['staff-only@example.com', PasswordResetChannel::Web],
            ['both@example.com', PasswordResetChannel::Web],
        ];

        foreach ($identifiers as [$identifier, $channel]) {
            $result = $service->request($identifier, $channel, SupportedLanguage::Spanish, '10.0.0.3');
            self::assertFalse($result->rateLimitedByIp);
        }
    }

    public function testUnconfirmedAndGoogleAccountsCanRequestAReset(): void
    {
        $unconfirmed = $this->user(status: UserStatus::UnconfirmedAccount, id: 9);
        $google = $this->user(passwordChosen: false, id: 10);
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByNickname')->willReturnOnConsecutiveCalls($unconfirmed, $google);
        $requestSender = $this->createMock(PasswordResetRequestSender::class);
        $requestSender->expects(self::exactly(2))->method('send');
        $service = $this->service($users, $this->tokens(), $this->manager(), $requestSender, $this->createStub(PasswordResetCompletedSender::class));

        self::assertFalse($service->request('ada', PasswordResetChannel::Web, SupportedLanguage::Spanish, '10.0.0.4')->rateLimitedByIp);
        self::assertFalse($service->request('ada', PasswordResetChannel::Web, SupportedLanguage::Spanish, '10.0.0.4')->rateLimitedByIp);
        self::assertSame(UserStatus::UnconfirmedAccount, $unconfirmed->getStatus());
        self::assertFalse($google->hasChosenPassword());
    }

    public function testCompleteKeepsStatusClearsTheLoginLockAndRejectsAUsedOrExpiredLink(): void
    {
        $user = $this->user(status: UserStatus::UnconfirmedAccount, passwordChosen: false);
        $user->setPassword('old-hash');
        $user->recordFailedLoginAttempt();
        $user->setNoLoginUntil(new DateTime('+1 hour'));
        $verifier = str_repeat('ab', 32);
        $token = new PasswordResetToken(
            $user,
            str_repeat('cd', 16),
            hash_hmac('sha256', $verifier, self::SECRET),
            PasswordResetChannel::Web,
            new DateTime('+2 hours'),
        );
        $expired = new PasswordResetToken(
            $user,
            str_repeat('ef', 16),
            hash_hmac('sha256', $verifier, self::SECRET),
            PasswordResetChannel::Web,
            new DateTime('-1 minute'),
        );
        $this->assignTokenId($token, 11);
        $this->assignTokenId($expired, 12);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::once())->method('hashPassword')->with($user, 'Newpass1')->willReturn('new-hash');
        $completed = $this->createMock(PasswordResetCompletedSender::class);
        $completed->expects(self::once())->method('send')->with(
            $user,
            PasswordResetChannel::Web,
            self::isInstanceOf(DateTime::class),
            SupportedLanguage::French,
        );
        $tokens = $this->createStub(PasswordResetTokenRepository::class);
        $tokens->method('findOneBySelector')->willReturnCallback(
            static function (string $selector) use ($token, $expired): ?PasswordResetToken {
                return match ($selector) {
                    $token->getSelector() => $token,
                    $expired->getSelector() => $expired,
                    default => null,
                };
            },
        );
        $tokens->method('claim')->willReturnCallback(
            static function (int $id, DateTime $now) use ($token, $expired): bool {
                $candidate = match ($id) {
                    11 => $token,
                    12 => $expired,
                    default => null,
                };

                return $candidate instanceof PasswordResetToken
                    && !$candidate->isConsumed()
                    && !$candidate->isExpired($now);
            },
        );
        $service = $this->service(
            $this->createStub(UserRepository::class),
            $tokens,
            $this->manager(),
            $this->createStub(PasswordResetRequestSender::class),
            $completed,
            hasher: $hasher,
        );
        $publicToken = $token->getSelector() . '.' . $verifier;

        self::assertNull($service->findOpen($expired->getSelector() . '.' . $verifier, PasswordResetChannel::Web));
        self::assertNull($service->findOpen($token->getSelector() . '.' . str_repeat('00', 32), PasswordResetChannel::Web));
        self::assertNull($service->findOpen('not-a-token', PasswordResetChannel::Web));
        self::assertFalse($service->complete($expired, 'Newpass1', SupportedLanguage::French));
        self::assertSame('old-hash', $user->getPassword());
        self::assertTrue($service->complete($token, 'Newpass1', SupportedLanguage::French));
        self::assertSame('new-hash', $user->getPassword());
        self::assertTrue($user->hasChosenPassword());
        self::assertSame(UserStatus::UnconfirmedAccount, $user->getStatus());
        self::assertSame(0, $user->getFailedLoginAttempts());
        self::assertNull($user->getNoLoginUntil());
        self::assertTrue($token->isConsumed());
        self::assertNull($service->findOpen($publicToken, PasswordResetChannel::Web));
        self::assertFalse($service->complete($token, 'Newpass1', SupportedLanguage::French));
    }

    public function testCompleteConsumesTheLinkWithoutChangingThePasswordWhenTheAccountCanNoLongerReset(): void
    {
        $banned = $this->user(status: UserStatus::Banned, id: 20);
        $banned->setPassword('old-hash');
        $bannedToken = $this->openToken($banned, PasswordResetChannel::Web, 20);
        $formerStaff = $this->user(roles: [UserRole::User], id: 21);
        $formerStaff->setPassword('old-hash');
        $adminToken = $this->openToken($formerStaff, PasswordResetChannel::Admin, 21);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::never())->method('hashPassword');
        $completed = $this->createMock(PasswordResetCompletedSender::class);
        $completed->expects(self::never())->method('send');
        $tokens = $this->createStub(PasswordResetTokenRepository::class);
        $tokens->method('claim')->willReturn(true);
        $service = $this->service(
            $this->createStub(UserRepository::class),
            $tokens,
            $this->manager(),
            $this->createStub(PasswordResetRequestSender::class),
            $completed,
            hasher: $hasher,
        );

        self::assertFalse($service->complete($bannedToken, 'Newpass1', SupportedLanguage::Spanish));
        self::assertSame('old-hash', $banned->getPassword());
        self::assertTrue($bannedToken->isConsumed());
        self::assertSame(UserStatus::Banned, $banned->getStatus());

        self::assertFalse($service->complete($adminToken, 'Newpass1', SupportedLanguage::Spanish));
        self::assertSame('old-hash', $formerStaff->getPassword());
        self::assertTrue($adminToken->isConsumed());
    }

    public function testIpLimitShowsADistinctResultAndIdentifierLimitStaysSilent(): void
    {
        $user = $this->user();
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByNickname')->willReturn($user);
        $ipSender = $this->createMock(PasswordResetRequestSender::class);
        $ipSender->expects(self::once())->method('send');
        $ipLimited = $this->service(
            $users,
            $this->tokens(),
            $this->manager(),
            $ipSender,
            $this->createStub(PasswordResetCompletedSender::class),
            ipLimit: 1,
        );

        self::assertFalse($ipLimited->request('ada', PasswordResetChannel::Web, SupportedLanguage::Spanish, '10.1.1.1')->rateLimitedByIp);
        self::assertTrue($ipLimited->request('ada', PasswordResetChannel::Web, SupportedLanguage::Spanish, '10.1.1.1')->rateLimitedByIp);

        $identifierSender = $this->createMock(PasswordResetRequestSender::class);
        $identifierSender->expects(self::once())->method('send');
        $identifierLimited = $this->service(
            $users,
            $this->tokens(),
            $this->manager(),
            $identifierSender,
            $this->createStub(PasswordResetCompletedSender::class),
            identifierLimit: 1,
        );

        self::assertFalse($identifierLimited->request('ada', PasswordResetChannel::Web, SupportedLanguage::Spanish, '10.1.1.2')->rateLimitedByIp);
        $silent = $identifierLimited->request('ada', PasswordResetChannel::Web, SupportedLanguage::Spanish, '10.1.1.3');
        self::assertFalse($silent->rateLimitedByIp);
    }

    /**
     * @param list<UserRole> $roles
     */
    private function user(
        UserStatus $status = UserStatus::Active,
        array $roles = [UserRole::User],
        ?string $email = 'ada@example.com',
        bool $passwordChosen = true,
        string $nickname = 'ada',
        int $id = 1,
    ): User {
        $user = new User()
            ->setName('Ada')
            ->setLastname('Tester')
            ->setNickname($nickname)
            ->setEmail($email)
            ->setApplicationRoles($roles)
            ->setStatus($status)
            ->setPasswordChosen($passwordChosen)
            ->setPassword('stored-hash');
        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }

    private function service(
        UserRepository $users,
        PasswordResetTokenRepository $tokens,
        EntityManagerInterface $manager,
        PasswordResetRequestSender $requestSender,
        PasswordResetCompletedSender $completedSender,
        int $ipLimit = 100,
        int $identifierLimit = 100,
        ?UserPasswordHasherInterface $hasher = null,
    ): PasswordResetService {
        $storage = new InMemoryStorage();

        return new PasswordResetService(
            $users,
            $tokens,
            $manager,
            $hasher ?? $this->createStub(UserPasswordHasherInterface::class),
            $requestSender,
            $completedSender,
            self::SECRET,
            $this->limiter('password_reset_request_ip', $ipLimit, $storage),
            $this->limiter('password_reset_request_identifier', $identifierLimit, $storage),
        );
    }

    private function limiter(string $id, int $limit, InMemoryStorage $storage): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => $id,
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => '1 hour',
        ], $storage);
    }

    private function openToken(User $user, PasswordResetChannel $channel, int $id): PasswordResetToken
    {
        $token = new PasswordResetToken(
            $user,
            str_repeat('ab', 16),
            hash_hmac('sha256', 'verifier', self::SECRET),
            $channel,
            new DateTime('+2 hours'),
        );
        $this->assignTokenId($token, $id);

        return $token;
    }

    private function assignTokenId(PasswordResetToken $token, int $id): void
    {
        $property = new ReflectionProperty(PasswordResetToken::class, 'id');
        $property->setValue($token, $id);
    }

    private function tokens(): PasswordResetTokenRepository
    {
        $tokens = $this->createStub(PasswordResetTokenRepository::class);
        $tokens->method('findUnconsumed')->willReturn([]);

        return $tokens;
    }

    private function manager(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }
}

final class PasswordResetTokenBox
{
    public ?PasswordResetToken $token = null;
}
