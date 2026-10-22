<?php

declare(strict_types=1);

namespace App\Tests\Web\Service;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\AccountConfirmationCode;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Legal\LegalAcceptanceService;
use App\Legal\LegalLanguageResolver;
use App\Notification\Email\AccountConfirmationCodeSender;
use App\Notification\StaffNewUserNotifier;
use App\Repository\AccountConfirmationCodeRepository;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserLegalAcceptanceRepository;
use App\Web\Registration\RegistrationData;
use App\Web\Security\ConfirmationCodeHasher;
use App\Web\Service\AccountConfirmationService;
use App\Web\Service\BrowserPreferenceResolver;
use App\Web\Service\PublicRegistrationService;
use App\Web\Service\ResendQuota;
use App\Web\Service\SignupLegalCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function preg_match;

#[CoversClass(PublicRegistrationService::class)]
#[Group('unit')]
final class PublicRegistrationServiceTest extends TestCase
{
    public function testRegistrationBuildsAnUnconfirmedUserFromTheSubmittedLanguage(): void
    {
        $settingsRepository = $this->createStub(GeneralSettingsRepository::class);
        $settingsRepository->method('findSingleton')->willReturn(GeneralSettings::createWithDefaults());
        $settings = new GeneralSettingsProvider($settingsRepository);
        $documents = $this->createStub(LegalDocumentRepository::class);
        $documents->method('findOneByType')->willReturn(null);
        $catalog = new SignupLegalCatalog(
            $documents,
            $this->createStub(LegalDocumentVersionRepository::class),
            new LegalLanguageResolver(),
            $settings,
        );
        $manager = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $manager->expects(self::exactly(2))->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );
        $manager->expects(self::once())->method('flush');
        $acceptances = new LegalAcceptanceService(
            $manager,
            $this->createStub(LegalDocumentVersionRepository::class),
            $this->createStub(UserLegalAcceptanceRepository::class),
            $this->createStub(GeneralSettingsRepository::class),
            new LegalLanguageResolver(),
        );
        $codes = $this->createStub(AccountConfirmationCodeRepository::class);
        $codes->method('findUnconsumed')->willReturn([]);
        $hasher = new ConfirmationCodeHasher('test');
        $sentCode = null;
        $sender = $this->createMock(AccountConfirmationCodeSender::class);
        $sender->expects(self::once())->method('send')->willReturnCallback(
            static function (User $user, string $code) use (&$sentCode): void {
                $sentCode = $code;
            },
        );
        $confirmations = new AccountConfirmationService(
            $codes,
            $manager,
            $hasher,
            $sender,
            $this->createStub(StaffNewUserNotifier::class),
            $this->createStub(ResendQuota::class),
            'test',
        );
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-secret');
        $service = new PublicRegistrationService(
            $manager,
            new BrowserPreferenceResolver(),
            $catalog,
            $acceptances,
            $confirmations,
            $sender,
            $passwordHasher,
            $settings,
        );
        $data = new RegistrationData();
        $data->name = 'Ada';
        $data->lastname = 'Lovelace';
        $data->email = 'Ada@Example.com';
        $data->password = 'Secret123';
        $data->timezone = 'America/Mexico_City';
        $data->locale = 'es-MX';
        $data->timeFormat = '24h';

        $result = $service->register($data, SupportedLanguage::English, '127.0.0.1', 'test-agent');

        $user = $result->user;
        self::assertTrue($result->emailSent);
        self::assertSame('ada@example.com', $user->getEmail());
        self::assertSame('ada@example.com', $user->getNickname());
        self::assertSame([UserRole::User], $user->getApplicationRoles());
        self::assertSame(UserStatus::UnconfirmedAccount, $user->getStatus());
        self::assertSame(SupportedLanguage::English, $user->getPreferredLanguage());
        self::assertSame('America/Mexico_City', $user->getTimezone());
        self::assertSame('hashed-secret', $user->getPassword());
        self::assertFalse($user->isHidden());
        self::assertIsString($sentCode);
        self::assertSame(1, preg_match('/^\d{6}$/', $sentCode));
        $storedCode = $persisted[1] ?? null;
        self::assertInstanceOf(AccountConfirmationCode::class, $storedCode);
        self::assertTrue($hasher->verify($sentCode, $storedCode->getCodeHash()));
        self::assertNotSame($sentCode, $storedCode->getCodeHash());
    }
}
