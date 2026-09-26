<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\DatabaseBackup;

use App\Admin\Service\DatabaseBackup\DatabaseBackupConnectionParser;
use App\Admin\Service\DatabaseBackup\DatabaseBackupExportException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseBackupConnectionParser::class)]
#[Group('unit')]
final class DatabaseBackupConnectionParserTest extends TestCase
{
    public function testParsesPostgresqlUrl(): void
    {
        $connection = new DatabaseBackupConnectionParser()->parse(
            'postgresql',
            'postgresql://app:s3cret%40p@database:5432/app_pokedex?serverVersion=18',
        );

        self::assertSame('postgresql', $connection->engine);
        self::assertSame('database', $connection->host);
        self::assertSame(5432, $connection->port);
        self::assertSame('app', $connection->user);
        self::assertSame('s3cret@p', $connection->password);
        self::assertSame('app_pokedex', $connection->databaseName);
    }

    public function testUsesMysqlDefaultPortWhenMissing(): void
    {
        $connection = new DatabaseBackupConnectionParser()->parse(
            'mysql',
            'mysql://root:root@127.0.0.1/app_pokedex',
        );

        self::assertSame(3306, $connection->port);
    }

    public function testRejectsInvalidUrl(): void
    {
        $this->expectException(DatabaseBackupExportException::class);

        new DatabaseBackupConnectionParser()->parse('postgresql', 'not-a-url');
    }
}
