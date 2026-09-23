<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

final readonly class DatabaseBackupConnection
{
    public function __construct(
        public string $engine,
        public string $host,
        public int $port,
        public string $user,
        public string $password,
        public string $databaseName,
    ) {
    }
}
