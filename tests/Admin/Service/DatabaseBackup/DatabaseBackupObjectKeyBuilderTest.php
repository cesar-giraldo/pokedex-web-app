<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\DatabaseBackup;

use App\Admin\Service\DatabaseBackup\DatabaseBackupObjectKeyBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseBackupObjectKeyBuilder::class)]
#[Group('unit')]
final class DatabaseBackupObjectKeyBuilderTest extends TestCase
{
    public function testBuildsPrivateDatabaseBackupsKey(): void
    {
        $builder = new DatabaseBackupObjectKeyBuilder('dev');

        self::assertSame(
            'dev/private/database-backups/backup-20260923T033045Z.sql',
            $builder->build('backup-20260923T033045Z.sql'),
        );
    }
}
