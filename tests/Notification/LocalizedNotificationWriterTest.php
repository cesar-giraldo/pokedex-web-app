<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Notification\LocalizedNotificationWriter;
use App\Notification\NotificationService;
use App\Repository\GeneralSettingsRepository;
use App\Repository\NotificationRepository;
use App\Tests\Notification\Support\CatalogTranslator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(LocalizedNotificationWriter::class)]
#[Group('unit')]
final class LocalizedNotificationWriterTest extends TestCase
{
    public function testSnapshotsEachRecipientInTheirPreferredLanguage(): void
    {
        $english = $this->user(1, SupportedLanguage::English);
        $french = $this->user(2, SupportedLanguage::French);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::exactly(2))->method('flush');

        $created = $this->writer(
            $entityManager,
            GeneralSettings::createWithDefaults()
                ->setEnabledLanguages(['es', 'en', 'fr'])
                ->setWebsiteDefaultLanguage('es'),
        )->notify(
            recipients: [$english, $french],
            type: NotificationType::DatabaseBackupCompleted,
            titleKey: 'notifications.snapshot.database_backup.completed.title',
            messageKey: 'notifications.snapshot.database_backup.completed.message',
            parameters: ['key' => 'backups/demo.sql'],
            actionUrl: '/admin/settings/general',
        );

        self::assertCount(2, $created);
        self::assertSame($english, $created[0]->getRecipient());
        self::assertSame('Database backup completed', $created[0]->getTitle());
        self::assertSame('The backup was created and uploaded to S3. Key: backups/demo.sql', $created[0]->getMessage());
        self::assertSame('/admin/settings/general', $created[0]->getActionUrl());
        self::assertSame($french, $created[1]->getRecipient());
        self::assertSame('Sauvegarde de la base de données terminée', $created[1]->getTitle());
        self::assertSame('La sauvegarde a été créée et envoyée vers S3. Clé : backups/demo.sql', $created[1]->getMessage());
    }

    public function testRecipientsWhoShareALanguageShareOneSnapshot(): void
    {
        $first = $this->user(1, SupportedLanguage::English);
        $second = $this->user(2, SupportedLanguage::English);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $created = $this->writer(
            $entityManager,
            GeneralSettings::createWithDefaults()
                ->setEnabledLanguages(['es', 'en'])
                ->setWebsiteDefaultLanguage('es'),
        )->notify(
            recipients: [$first, $second],
            type: NotificationType::PokemonsImported,
            titleKey: 'notifications.snapshot.pokemons_imported.title',
            messageKey: 'notifications.snapshot.pokemons_imported.message',
            parameters: ['count' => 2],
        );

        self::assertCount(2, $created);
        self::assertSame('2 Pokémon were added', $created[0]->getTitle());
        self::assertSame('The import command saved 2 Pokémon to the database.', $created[0]->getMessage());
        self::assertSame($created[0]->getTitle(), $created[1]->getTitle());
        self::assertSame($created[0]->getMessage(), $created[1]->getMessage());
    }

    public function testDisabledPreferredLanguageUsesThePlatformDefault(): void
    {
        $user = $this->user(1, SupportedLanguage::French);

        $created = $this->writer(
            $this->createStub(EntityManagerInterface::class),
            GeneralSettings::createWithDefaults()
                ->setEnabledLanguages(['es', 'en'])
                ->setWebsiteDefaultLanguage('es'),
        )->notify(
            recipients: [$user],
            type: NotificationType::DatabaseBackupFailed,
            titleKey: 'notifications.snapshot.database_backup.failed.title',
            messageKey: 'notifications.snapshot.database_backup.failed.unexpected',
        );

        self::assertSame('Falló el backup de base de datos', $created[0]->getTitle());
        self::assertSame('No se pudo generar el backup de la base de datos.', $created[0]->getMessage());
    }

    public function testEmptyRecipientListDoesNotPersist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $repository = $this->createMock(GeneralSettingsRepository::class);
        $repository->expects(self::never())->method('findSingleton');

        $writer = new LocalizedNotificationWriter(
            new NotificationService($entityManager, $this->createStub(NotificationRepository::class)),
            CatalogTranslator::create(),
            new LegalLanguageResolver(),
            new GeneralSettingsProvider($repository),
        );

        self::assertSame([], $writer->notify(
            recipients: [],
            type: NotificationType::PokemonsImported,
            titleKey: 'notifications.snapshot.pokemons_imported.title',
            messageKey: 'notifications.snapshot.pokemons_imported.message',
            parameters: ['count' => 1],
        ));
    }

    private function writer(EntityManagerInterface $entityManager, GeneralSettings $settings): LocalizedNotificationWriter
    {
        $repository = $this->createStub(GeneralSettingsRepository::class);
        $repository->method('findSingleton')->willReturn($settings);

        return new LocalizedNotificationWriter(
            new NotificationService($entityManager, $this->createStub(NotificationRepository::class)),
            CatalogTranslator::create(),
            new LegalLanguageResolver(),
            new GeneralSettingsProvider($repository),
        );
    }

    private function user(int $id, SupportedLanguage $language): User
    {
        $user = new User()
            ->setNickname('user-' . $id)
            ->setPreferredLanguage($language);
        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
