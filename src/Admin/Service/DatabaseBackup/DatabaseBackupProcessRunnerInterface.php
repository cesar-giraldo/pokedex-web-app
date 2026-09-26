<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

interface DatabaseBackupProcessRunnerInterface
{
    /**
     * @param list<string>          $command
     * @param array<string, string> $env
     */
    public function run(array $command, array $env): void;
}
