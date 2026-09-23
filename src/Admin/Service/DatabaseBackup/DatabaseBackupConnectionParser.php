<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

use function is_array;
use function ltrim;
use function parse_url;
use function rawurldecode;
use function sprintf;

final class DatabaseBackupConnectionParser
{
    public function parse(string $engine, string $databaseUrl): DatabaseBackupConnection
    {
        $parts = parse_url($databaseUrl);

        if (!is_array($parts) || !isset($parts['host']) || '' === $parts['host']) {
            throw new DatabaseBackupExportException('DATABASE_URL no es válida.');
        }

        $databaseName = ltrim($parts['path'] ?? '', '/');

        if ('' === $databaseName) {
            throw new DatabaseBackupExportException('DATABASE_URL no incluye el nombre de la base de datos.');
        }

        return new DatabaseBackupConnection(
            engine: $engine,
            host: $parts['host'],
            port: isset($parts['port']) ? (int) $parts['port'] : $this->defaultPort($engine),
            user: rawurldecode($parts['user'] ?? ''),
            password: rawurldecode($parts['pass'] ?? ''),
            databaseName: $databaseName,
        );
    }

    private function defaultPort(string $engine): int
    {
        return match ($engine) {
            'mysql' => 3306,
            'postgresql' => 5432,
            default => throw new DatabaseBackupExportException(sprintf(
                'Motor de base de datos no soportado: %s.',
                $engine,
            )),
        };
    }
}
