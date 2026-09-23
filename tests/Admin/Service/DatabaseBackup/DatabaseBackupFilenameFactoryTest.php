<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\DatabaseBackup;

use App\Admin\Service\DatabaseBackup\DatabaseBackupFilenameFactory;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseBackupFilenameFactory::class)]
#[Group('unit')]
final class DatabaseBackupFilenameFactoryTest extends TestCase
{
    public function testCreatesUtcFilename(): void
    {
        $generatedAt = new DateTimeImmutable('2026-09-23 03:30:45', new DateTimeZone('UTC'));

        self::assertSame(
            'backup-20260923T033045Z.sql',
            new DatabaseBackupFilenameFactory()->create($generatedAt),
        );
    }

    public function testConvertsNonUtcInstantToUtc(): void
    {
        $generatedAt = new DateTimeImmutable('2026-09-22 22:30:45', new DateTimeZone('America/Bogota'));

        self::assertSame(
            'backup-20260923T033045Z.sql',
            new DatabaseBackupFilenameFactory()->create($generatedAt),
        );
    }
}
