<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

interface DatabaseBackupUploaderInterface
{
    public function upload(string $objectKey, string $localPath): void;
}
