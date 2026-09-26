<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function basename;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

final class DatabaseBackupObjectKeyBuilder
{
    public function __construct(
        #[Autowire('%env(AWS_S3_STORAGE_PREFIX)%')]
        private readonly string $storagePrefix,
    ) {
    }

    public function build(string $filename): string
    {
        return $this->directoryPrefix() . $filename;
    }

    public function isAllowedKey(string $objectKey): bool
    {
        $directoryPrefix = $this->directoryPrefix();

        if (!str_starts_with($objectKey, $directoryPrefix)) {
            return false;
        }

        $filename = substr($objectKey, strlen($directoryPrefix));

        return $filename === basename($filename)
            && 1 === preg_match(DatabaseBackupFilenameFactory::FILENAME_PATTERN, $filename);
    }

    private function directoryPrefix(): string
    {
        return sprintf('%s/private/database-backups/', $this->storagePrefix);
    }
}
