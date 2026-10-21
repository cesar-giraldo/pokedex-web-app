<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\AccountConfirmationCode;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Notification\Email\AccountConfirmationCodeSender;
use App\Notification\StaffNewUserNotifier;
use App\Repository\AccountConfirmationCodeRepository;
use App\Web\Registration\ConfirmationAttempt;
use App\Web\Security\ConfirmationCodeHasher;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function preg_match;
use function random_int;
use function str_pad;
use function trim;

use const STR_PAD_LEFT;

final class AccountConfirmationService
{
    public const int RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly AccountConfirmationCodeRepository $codes,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfirmationCodeHasher $hasher,
        private readonly AccountConfirmationCodeSender $codeSender,
        private readonly StaffNewUserNotifier $notifier,
        private readonly ResendQuota $resendQuota,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function issue(User $user): string
    {
        $this->consumeOutstanding($user);
        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = new DateTime('+' . AccountConfirmationCode::TTL_HOURS . ' hours');
        $code = new AccountConfirmationCode($user, $this->hasher->hash($plainCode), $expiresAt);
        $this->entityManager->persist($code);

        return $plainCode;
    }

    public function verify(User $user, string $plainCode): ConfirmationAttempt
    {
        if (UserStatus::UnconfirmedAccount !== $user->getStatus()) {
            return ConfirmationAttempt::Missing;
        }

        $active = $this->activeCode($user);
        $now = new DateTime();

        if (!$active instanceof AccountConfirmationCode) {
            return ConfirmationAttempt::Missing;
        }

        if ($active->isExpired($now)) {
            $active->consume($now);
            $this->entityManager->flush();

            return ConfirmationAttempt::Expired;
        }

        if ($active->getAttempts() >= AccountConfirmationCode::MAX_ATTEMPTS) {
            $active->consume($now);
            $this->entityManager->flush();

            return ConfirmationAttempt::Exhausted;
        }

        $normalized = trim($plainCode);

        if (1 !== preg_match('/^\d{6}$/', $normalized) || !$this->hasher->verify($normalized, $active->getCodeHash())) {
            $active->registerFailedAttempt();

            if ($active->getAttempts() >= AccountConfirmationCode::MAX_ATTEMPTS) {
                $active->consume($now);
                $this->entityManager->flush();

                return ConfirmationAttempt::Exhausted;
            }

            $this->entityManager->flush();

            return ConfirmationAttempt::Mismatch;
        }

        $active->consume($now);
        $user->setStatus(UserStatus::UncompleteProfileInfo);
        $this->entityManager->flush();
        $this->notifier->notify($user);

        return ConfirmationAttempt::Confirmed;
    }

    public function resend(User $user): ConfirmationAttempt
    {
        if (UserStatus::UnconfirmedAccount !== $user->getStatus()) {
            return ConfirmationAttempt::Missing;
        }

        $latest = $this->codes->findLatest($user);

        if ($latest instanceof AccountConfirmationCode && $this->isCoolingDown($latest)) {
            return ConfirmationAttempt::Cooldown;
        }

        if (!$this->resendQuota->allow($user)) {
            return ConfirmationAttempt::Limited;
        }

        $plainCode = $this->issue($user);
        $this->entityManager->flush();

        try {
            $this->codeSender->send($user, $plainCode);
        } catch (Throwable $exception) {
            $this->logger->error('No se pudo reenviar el código de confirmación.', [
                'user_id' => $user->getId(),
                'exception' => $exception,
            ]);

            return ConfirmationAttempt::MailFailed;
        }

        return ConfirmationAttempt::Resent;
    }

    private function activeCode(User $user): ?AccountConfirmationCode
    {
        $codes = $this->codes->findUnconsumed($user);

        return $codes[0] ?? null;
    }

    private function consumeOutstanding(User $user): void
    {
        $now = new DateTime();

        foreach ($this->codes->findUnconsumed($user) as $code) {
            $code->consume($now);
        }
    }

    private function isCoolingDown(AccountConfirmationCode $code): bool
    {
        $elapsed = new DateTime()->getTimestamp() - $code->getCreatedAt()->getTimestamp();

        return $elapsed < $this->cooldownSeconds();
    }

    private function cooldownSeconds(): int
    {
        return 'test' === $this->environment ? 0 : self::RESEND_COOLDOWN_SECONDS;
    }
}
