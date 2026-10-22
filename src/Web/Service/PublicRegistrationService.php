<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\LegalAcceptanceMethod;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;
use App\Legal\LegalAcceptanceService;
use App\Notification\Email\AccountConfirmationCodeSender;
use App\Web\Registration\AccountAlreadyExistsException;
use App\Web\Registration\RegistrationData;
use App\Web\Registration\RegistrationResult;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Throwable;

use function mb_strtolower;
use function trim;

final class PublicRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BrowserPreferenceResolver $preferences,
        private readonly SignupLegalCatalog $legalCatalog,
        private readonly LegalAcceptanceService $acceptances,
        private readonly AccountConfirmationService $confirmations,
        private readonly AccountConfirmationCodeSender $codeSender,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly GeneralSettingsProvider $settings,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function register(
        RegistrationData $data,
        SupportedLanguage $language,
        ?string $ipAddress,
        ?string $userAgent,
    ): RegistrationResult {
        $resolved = $this->preferences->resolve(
            $data->timezone,
            $data->locale,
            $data->timeFormat,
            $this->settings->get(),
        );
        $email = mb_strtolower(trim($data->email));
        $user = new User();
        $user->setName(trim($data->name));
        $user->setLastname(trim($data->lastname));
        $user->setEmail($email);
        $user->setNickname($email);
        $user->setApplicationRoles([UserRole::User]);
        $user->setStatus(UserStatus::UnconfirmedAccount);
        $user->setPreferredLanguage($language);
        $user->setTimezone($resolved->timezone);
        $user->setLocale($resolved->locale);
        $user->setTimeFormat($resolved->timeFormat);
        $user->setIsHidden(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->password));

        $this->entityManager->persist($user);
        $this->acceptances->recordAcceptances(
            $user,
            $this->translations($language),
            LegalAcceptanceMethod::Signup,
            $ipAddress,
            $userAgent,
        );
        $plainCode = $this->confirmations->issue($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new AccountAlreadyExistsException();
        }

        try {
            $this->codeSender->send($user, $plainCode);
        } catch (Throwable $exception) {
            $this->logger->error('No se pudo enviar el código de confirmación.', [
                'user_id' => $user->getId(),
                'exception' => $exception,
            ]);

            return new RegistrationResult($user, false);
        }

        return new RegistrationResult($user, true);
    }

    /**
     * @return list<LegalDocumentVersionTranslation>
     */
    private function translations(SupportedLanguage $language): array
    {
        $translations = [];

        foreach ($this->legalCatalog->available($language) as $offer) {
            $translations[] = $offer->translation;
        }

        return $translations;
    }
}
