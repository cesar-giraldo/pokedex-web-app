<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function sprintf;

final class DatabaseBackupObjectKeyBuilder
{
    public function __construct(
        #[Autowire('%env(AWS_S3_STORAGE_PREFIX)%')]
        private readonly string $storagePrefix,
    ) {
    }

    public function build(string $filename): string
    {
        return sprintf('%s/private/database-backups/%s', $this->storagePrefix, $filename);
    }
}
