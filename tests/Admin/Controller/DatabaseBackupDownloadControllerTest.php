<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Admin\Service\Storage\ObjectStorage;
use App\Repository\GeneralSettingsRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class DatabaseBackupDownloadControllerTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    private const string OBJECT_KEY = 'test/private/database-backups/backup-20260923T033045Z.sql';

    private const string POISONED_OBJECT_KEY = 'test/private/user/profile-images/1/secret.txt';

    private ?string $previousBackupPath = null;

    private ?DateTimeInterface $previousBackupGeneratedAt = null;

    private bool $wroteBackupObject = false;

    private bool $wrotePoisonedObject = false;

    protected function tearDown(): void
    {
        if (self::$booted) {
            $container = static::getContainer();

            /** @var GeneralSettingsRepository $repository */
            $repository = $container->get(GeneralSettingsRepository::class);
            $settings = $repository->findSingleton();

            if (null !== $settings && null !== $settings->getId()) {
                /** @var EntityManagerInterface $entityManager */
                $entityManager = $container->get(EntityManagerInterface::class);
                $entityManager->getConnection()->update(
                    'general_settings',
                    [
                        'last_backup_file_path' => $this->previousBackupPath,
                        'last_backup_generated_at' => $this->previousBackupGeneratedAt,
                    ],
                    ['id' => $settings->getId()],
                    [
                        'last_backup_file_path' => Types::STRING,
                        'last_backup_generated_at' => Types::DATETIME_MUTABLE,
                    ],
                );
            }

            if ($this->wroteBackupObject || $this->wrotePoisonedObject) {
                /** @var ObjectStorage $objectStorage */
                $objectStorage = $container->get(ObjectStorage::class);

                if ($this->wroteBackupObject) {
                    $objectStorage->tryDelete(self::OBJECT_KEY);
                }

                if ($this->wrotePoisonedObject) {
                    $objectStorage->tryDelete(self::POISONED_OBJECT_KEY);
                }
            }
        }

        parent::tearDown();
    }

    public function testDeveloperCanDownloadExistingBackup(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->seedBackupFile();

        $client->request('GET', '/admin/database-backup/download');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/sql');
        self::assertStringContainsString(
            'backup-20260923T033045Z.sql',
            (string) $client->getResponse()->headers->get('Content-Disposition'),
        );
        self::assertSame('-- dump contents', $client->getInternalResponse()->getContent());
    }

    public function testAdminCanDownloadExistingBackup(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->seedBackupFile();

        $client->request('GET', '/admin/database-backup/download');

        self::assertResponseIsSuccessful();
    }

    public function testOperatorCannotDownloadBackup(): void
    {
        $client = static::createClient();
        $this->loginAsOperator($client);
        $this->seedBackupFile();

        $client->request('GET', '/admin/database-backup/download');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDownloadWithoutBackupReturnsNotFound(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->clearBackupPointer();

        $client->request('GET', '/admin/database-backup/download');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeveloperSettingsPageShowsEmptyBackupState(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->clearBackupPointer();

        $client->request('GET', '/admin/settings/general');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#last-database-backup h4', 'Último Backup');
        self::assertSelectorTextContains(
            '#last-database-backup',
            'No se ha generado ningún backup de la base de datos.',
        );
        self::assertSelectorNotExists('[aria-label="Descargar archivo de backup"]');
    }

    public function testDeveloperSettingsPageShowsDownloadWhenBackupExists(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->seedBackupFile();

        $client->request('GET', '/admin/settings/general');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[aria-label="Descargar archivo de backup"]');
        self::assertSelectorExists('#database-backup-confirm-dialog[data-controller="component-confirm-dialog"]');
        self::assertSelectorTextContains('#database-backup-confirm-dialog', 'Confirma que deseas descargar el archivo de backup');
    }

    public function testDownloadRejectsPoisonedObjectKeyOutsideBackupPrefix(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->seedPoisonedBackupPointer();

        $client->request('GET', '/admin/database-backup/download');

        self::assertResponseStatusCodeSame(404);
    }

    private function seedBackupFile(): void
    {
        $this->capturePreviousBackupPointer();

        /** @var ObjectStorage $objectStorage */
        $objectStorage = static::getContainer()->get(ObjectStorage::class);
        $objectStorage->write(self::OBJECT_KEY, '-- dump contents');
        $this->wroteBackupObject = true;

        /** @var GeneralSettingsRepository $repository */
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        $repository->recordLastDatabaseBackup(
            self::OBJECT_KEY,
            new DateTimeImmutable('2026-09-23 03:30:45', new DateTimeZone('UTC')),
        );
    }

    private function seedPoisonedBackupPointer(): void
    {
        $this->capturePreviousBackupPointer();

        /** @var ObjectStorage $objectStorage */
        $objectStorage = static::getContainer()->get(ObjectStorage::class);
        $objectStorage->write(self::POISONED_OBJECT_KEY, 'should-not-be-streamed');
        $this->wrotePoisonedObject = true;

        /** @var GeneralSettingsRepository $repository */
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        $repository->recordLastDatabaseBackup(
            self::POISONED_OBJECT_KEY,
            new DateTimeImmutable('2026-09-23 03:30:45', new DateTimeZone('UTC')),
        );
    }

    private function clearBackupPointer(): void
    {
        $this->capturePreviousBackupPointer();

        /** @var GeneralSettingsRepository $repository */
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        $settings = $repository->getOrCreateSingleton();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        if (null === $settings->getId()) {
            $entityManager->flush();
        }

        $entityManager->getConnection()->update(
            'general_settings',
            [
                'last_backup_file_path' => null,
                'last_backup_generated_at' => null,
            ],
            ['id' => $settings->getId()],
        );
        $entityManager->refresh($settings);
    }

    private function capturePreviousBackupPointer(): void
    {
        if (null !== $this->previousBackupPath || null !== $this->previousBackupGeneratedAt) {
            return;
        }

        /** @var GeneralSettingsRepository $repository */
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        $settings = $repository->getOrCreateSingleton();

        if (null === $settings->getId()) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->flush();
        }

        $this->previousBackupPath = $settings->getLastBackupFilePath();
        $this->previousBackupGeneratedAt = $settings->getLastBackupGeneratedAt();
    }
}
