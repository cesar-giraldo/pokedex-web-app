<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsAlias(DatabaseBackupProcessRunnerInterface::class)]
final class DatabaseBackupProcessRunner implements DatabaseBackupProcessRunnerInterface
{
    /**
     * @param list<string>          $command
     * @param array<string, string> $env
     */
    public function run(array $command, array $env): void
    {
        $process = new Process($command, env: $env, timeout: 0);
        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        throw new DatabaseBackupExportException(
            'No se pudo exportar la base de datos. Verifica que pg_dump o mysqldump estén instalados y que la conexión sea válida.',
            previous: new ProcessFailedException($process),
        );
    }
}
