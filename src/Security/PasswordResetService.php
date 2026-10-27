<?php

declare(strict_types=1);

namespace App\Security;

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
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function bin2hex;
use function count;
use function explode;
use function hash;
use function hash_equals;
use function hash_hmac;
use function in_array;
use function preg_match;
use function random_bytes;
use function str_contains;
use function trim;

final class PasswordResetService
{
    private const string PUBLIC_TOKEN_PATTERN = '/^[a-f0-9]{32}\.[a-f0-9]{64}$/';

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordResetRequestSender $requestSender,
        private readonly PasswordResetCompletedSender $completedSender,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
        #[Autowire(service: 'limiter.password_reset_request_ip')]
        private readonly RateLimiterFactory $ipLimiter,
        #[Autowire(service: 'limiter.password_reset_request_identifier')]
        private readonly RateLimiterFactory $identifierLimiter,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function request(
        string $identifier,
        PasswordResetChannel $channel,
        SupportedLanguage $requestLanguage,
        string $clientIp,
    ): PasswordResetRequestResult {
        $normalized = User::normalizeNickname($identifier);
        $ipKey = '' !== $clientIp ? $clientIp : 'unknown';

        if (!$this->ipLimiter->create($ipKey)->consume()->isAccepted()) {
            return PasswordResetRequestResult::rateLimited();
        }

        if ('' === $normalized) {
            return PasswordResetRequestResult::accepted();
        }

        if (!$this->identifierLimiter->create(hash('sha256', $normalized))->consume()->isAccepted()) {
            return PasswordResetRequestResult::accepted();
        }

        $user = $this->resolveUser($normalized, $channel);

        if (!$user instanceof User || !$this->canReset($user, $channel)) {
            $this->maskTiming($normalized);

            if ($user instanceof User) {
                $this->logger->info('Se omitió la recuperación de contraseña.', [
                    'user_id' => $user->getId(),
                    'channel' => $channel->value,
                ]);
            }

            return PasswordResetRequestResult::accepted();
        }

        $publicToken = $this->issue($user, $channel);
        $this->requestSender->send($user, $publicToken, $channel, $requestLanguage);
        $this->logger->info('Se envió el enlace para restablecer la contraseña.', [
            'user_id' => $user->getId(),
            'channel' => $channel->value,
        ]);

        return PasswordResetRequestResult::accepted();
    }

    public function findOpen(string $publicToken, PasswordResetChannel $channel): ?PasswordResetToken
    {
        if (1 !== preg_match(self::PUBLIC_TOKEN_PATTERN, $publicToken)) {
            return null;
        }

        $parts = explode('.', $publicToken, 2);

        if (2 !== count($parts)) {
            return null;
        }

        [$selector, $verifier] = $parts;
        $token = $this->tokens->findOneBySelector($selector);

        if (!$token instanceof PasswordResetToken || $token->getChannel() !== $channel) {
            return null;
        }

        if ($token->isConsumed() || $token->isExpired(new DateTime())) {
            return null;
        }

        $expected = hash_hmac('sha256', $verifier, $this->appSecret);

        if (!hash_equals($token->getVerifierHash(), $expected)) {
            return null;
        }

        return $token;
    }

    public function complete(
        PasswordResetToken $token,
        string $plainPassword,
        SupportedLanguage $requestLanguage,
    ): bool {
        $tokenId = $token->getId();

        if (null === $tokenId) {
            return false;
        }

        $now = new DateTime();
        $user = $token->getUser();
        $this->entityManager->beginTransaction();

        try {
            if (!$this->tokens->claim($tokenId, $now)) {
                $this->entityManager->rollback();

                return false;
            }

            if ($this->entityManager->contains($token)) {
                $this->entityManager->refresh($token);
            }

            $token->consume($now);

            if ($this->entityManager->contains($user)) {
                $this->entityManager->refresh($user);
            }

            if (!$this->canReset($user, $token->getChannel())) {
                $this->entityManager->commit();

                return false;
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $user->setPasswordChosen(true);
            $user->setPasswordUpdatedAt($now);
            $user->resetFailedLoginAttempts();
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->rollBackOpenTransaction();

            throw $exception;
        }

        $this->completedSender->send($user, $token->getChannel(), $now, $requestLanguage);
        $this->logger->info('Se restableció la contraseña.', [
            'user_id' => $user->getId(),
            'channel' => $token->getChannel()->value,
        ]);

        return true;
    }

    private function resolveUser(string $normalized, PasswordResetChannel $channel): ?User
    {
        if (!str_contains($normalized, '@')) {
            return $this->users->findOneByNickname($normalized);
        }

        $byEmail = $this->users->findOneByEmail($normalized);
        $byNickname = $this->users->findOneByNickname($normalized);

        if ($byEmail instanceof User && $byNickname instanceof User && $byEmail->getId() !== $byNickname->getId()) {
            $this->logger->info('Se omitió la recuperación de contraseña porque el identificador coincide con dos cuentas.', [
                'channel' => $channel->value,
            ]);

            return null;
        }

        return $byEmail ?? $byNickname;
    }

    private function canReset(User $user, PasswordResetChannel $channel): bool
    {
        $email = $user->getEmail();

        if (null === $email || '' === trim($email)) {
            return false;
        }

        if (!in_array($user->getStatus(), [
            UserStatus::Active,
            UserStatus::UncompleteProfileInfo,
            UserStatus::UnconfirmedAccount,
        ], true)) {
            return false;
        }

        return match ($channel) {
            PasswordResetChannel::Web => $user->hasAssignedRole(UserRole::User),
            PasswordResetChannel::Admin => $user->hasBackendAccess(),
        };
    }

    private function issue(User $user, PasswordResetChannel $channel): string
    {
        $now = new DateTime();

        foreach ($this->tokens->findUnconsumed($user, $channel) as $outstanding) {
            $outstanding->consume($now);
        }

        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        $token = new PasswordResetToken(
            $user,
            $selector,
            hash_hmac('sha256', $verifier, $this->appSecret),
            $channel,
            new DateTime('+' . PasswordResetToken::TTL_HOURS . ' hours'),
            $now,
        );
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $selector . '.' . $verifier;
    }

    private function rollBackOpenTransaction(): void
    {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            return;
        }

        $this->entityManager->rollback();
    }

    private function maskTiming(string $normalized): void
    {
        $known = hash_hmac('sha256', 'unavailable', $this->appSecret);
        $actual = hash_hmac('sha256', $normalized, $this->appSecret);

        if (hash_equals($known, $actual)) {
            return;
        }
    }
}
