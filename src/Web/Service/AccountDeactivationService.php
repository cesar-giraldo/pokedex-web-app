<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Admin\Service\DateTimeFormatter;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function in_array;
use function is_string;
use function sprintf;

final class AccountDeactivationService
{
    public const string LOGOUT_NOTICE = 'account_deactivated';

    public const string MANUAL_NOTE = 'El usuario desactivó manualmente su cuenta desde la web.';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly DateTimeFormatter $dateTimeFormatter,
    ) {
    }

    /**
     * @return non-empty-string|null Clave de traducción cuando la cuenta no cambia
     */
    public function deactivate(User $user, mixed $currentPassword, bool $impersonating): ?string
    {
        if ($impersonating || !$this->canDeactivate($user)) {
            return 'account.deactivate_forbidden';
        }

        if ($user->hasChosenPassword()) {
            $password = is_string($currentPassword) ? $currentPassword : '';

            if ('' === $password || !$this->passwordHasher->isPasswordValid($user, $password)) {
                return 'account.deactivate_password_invalid';
            }
        }

        $formatted = $this->dateTimeFormatter->format(new DateTimeImmutable(), viewer: $user);
        $user->setStatus(UserStatus::Inactive);
        $user->appendAdditionalNote(sprintf('%s — %s', $formatted, self::MANUAL_NOTE));
        $this->entityManager->flush();

        return null;
    }

    private function canDeactivate(User $user): bool
    {
        if ($user->hasBackendAccess()) {
            return false;
        }

        return in_array($user->getStatus(), [
            UserStatus::Active,
            UserStatus::UncompleteProfileInfo,
        ], true);
    }
}
