<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\ObjectStorageException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(DatabaseBackupUploaderInterface::class)]
final class DatabaseBackupUploader implements DatabaseBackupUploaderInterface
{
    public function __construct(
        private readonly ObjectStorage $objectStorage,
    ) {
    }

    public function upload(string $objectKey, string $localPath): void
    {
        try {
            $this->objectStorage->writeFromPath($objectKey, $localPath);
        } catch (ObjectStorageException $exception) {
            throw new DatabaseBackupExportException($exception->getMessage(), previous: $exception);
        }
    }
}
