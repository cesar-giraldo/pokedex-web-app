<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Notification\StaffNewUserNotifier;
use App\Repository\UserRepository;
use App\Web\Security\GoogleIdentity;
use App\Web\Security\GoogleSignInException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function bin2hex;
use function is_string;
use function mb_substr;
use function random_bytes;
use function strstr;
use function trim;

final class GoogleAccountConnector
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly GeneralSettingsProvider $settings,
        private readonly StaffNewUserNotifier $notifier,
    ) {
    }

    public function connect(GoogleIdentity $identity, SupportedLanguage $language): User
    {
        $bySub = $this->users->findOneByGoogleSub($identity->sub);

        if ($bySub instanceof User) {
            return $this->signIn($bySub);
        }

        $byEmail = $this->users->findOneByEmail($identity->email);

        if ($byEmail instanceof User) {
            return $this->link($byEmail, $identity);
        }

        return $this->create($identity, $language);
    }

    private function signIn(User $user): User
    {
        if (!$this->canEnter($user)) {
            throw new GoogleSignInException('The Google account cannot be used.');
        }

        $this->promote($user);
        $this->save();

        return $user;
    }

    private function link(User $user, GoogleIdentity $identity): User
    {
        if (!$this->canEnter($user)) {
            throw new GoogleSignInException('The Google account cannot be linked.');
        }

        $current = $user->getGoogleSub();

        if (null !== $current && $current !== $identity->sub) {
            throw new GoogleSignInException('The Google account cannot be linked.');
        }

        $user->setGoogleSub($identity->sub);
        $this->promote($user);
        $this->save();

        return $user;
    }

    private function create(GoogleIdentity $identity, SupportedLanguage $language): User
    {
        if ($this->users->existsByNickname($identity->email)) {
            throw new GoogleSignInException('The Google account cannot be used.');
        }

        $settings = $this->settings->get();
        $user = new User();
        $user->setName($this->name($identity));
        $user->setLastname($this->lastname($identity));
        $user->setEmail($identity->email);
        $user->setNickname($identity->email);
        $user->setGoogleSub($identity->sub);
        $user->setApplicationRoles([UserRole::User]);
        $user->setStatus(UserStatus::UncompleteProfileInfo);
        $user->setPreferredLanguage($language);
        $user->setTimezone($settings->getDefaultTimezone());
        $user->setLocale($settings->getDefaultLocale());
        $user->setTimeFormat($settings->getDefaultTimeFormat());
        $user->setIsHidden(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->entityManager->persist($user);
        $this->save();
        $this->notifier->notify($user);

        return $user;
    }

    private function canEnter(User $user): bool
    {
        if (true === $user->isHidden() || !$user->hasAssignedRole(UserRole::User)) {
            return false;
        }

        foreach ($user->getApplicationRoles() as $role) {
            if ($role->grantsBackendAccess()) {
                return false;
            }
        }

        return match ($user->getStatus()) {
            UserStatus::Active, UserStatus::UncompleteProfileInfo, UserStatus::UnconfirmedAccount => true,
            UserStatus::Banned, UserStatus::Inactive => false,
        };
    }

    private function promote(User $user): void
    {
        if (UserStatus::UnconfirmedAccount === $user->getStatus()) {
            $user->setStatus(UserStatus::UncompleteProfileInfo);
        }
    }

    private function save(): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new GoogleSignInException('The Google account cannot be used.');
        }
    }

    private function name(GoogleIdentity $identity): string
    {
        $given = trim($identity->givenName);

        if ('' === $given) {
            $local = strstr($identity->email, '@', true);
            $given = is_string($local) ? trim($local) : '';
        }

        if ('' === $given) {
            $given = 'User';
        }

        return mb_substr($given, 0, 100);
    }

    private function lastname(GoogleIdentity $identity): string
    {
        $family = trim($identity->familyName);

        if ('' === $family) {
            $family = '-';
        }

        return mb_substr($family, 0, 100);
    }
}
