<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

use function filesize;
use function is_file;
use function is_int;
use function sprintf;

final class DatabaseBackupDumpCommandFactory
{
    /**
     * @return array{command: list<string>, env: array<string, string>}
     */
    public function create(DatabaseBackupConnection $connection, string $outputPath): array
    {
        return match ($connection->engine) {
            'postgresql' => $this->postgresql($connection, $outputPath),
            'mysql' => $this->mysql($connection, $outputPath),
            default => throw new DatabaseBackupExportException(sprintf(
                'Motor de base de datos no soportado: %s.',
                $connection->engine,
            )),
        };
    }

    /**
     * @return array{command: list<string>, env: array<string, string>}
     */
    private function postgresql(DatabaseBackupConnection $connection, string $outputPath): array
    {
        return [
            'command' => [
                'pg_dump',
                '--no-owner',
                '--no-acl',
                '--format=plain',
                '--file=' . $outputPath,
                '--host=' . $connection->host,
                '--port=' . (string) $connection->port,
                '--username=' . $connection->user,
                '--dbname=' . $connection->databaseName,
            ],
            'env' => [
                'PGPASSWORD' => $connection->password,
                'PGSSLMODE' => 'prefer',
            ],
        ];
    }

    /**
     * @return array{command: list<string>, env: array<string, string>}
     */
    private function mysql(DatabaseBackupConnection $connection, string $outputPath): array
    {
        return [
            'command' => [
                'mysqldump',
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--result-file=' . $outputPath,
                '--host=' . $connection->host,
                '--port=' . (string) $connection->port,
                '--user=' . $connection->user,
                $connection->databaseName,
            ],
            'env' => [
                'MYSQL_PWD' => $connection->password,
            ],
        ];
    }

    public function assertDumpFileIsValid(string $outputPath): void
    {
        $size = is_file($outputPath) ? filesize($outputPath) : false;

        if (!is_int($size) || $size <= 0) {
            throw new DatabaseBackupExportException('El archivo de backup generado está vacío o no existe.');
        }
    }
}
