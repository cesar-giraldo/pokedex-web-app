<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\DatabaseBackup;

use App\Admin\Service\DatabaseBackup\DatabaseBackupObjectKeyBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertTrue($builder->isAllowedKey('dev/private/database-backups/backup-20260923T033045Z.sql'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectedObjectKeys(): iterable
    {
        yield 'other private prefix' => ['dev/private/user/profile-images/1/avatar.jpg'];
        yield 'path traversal' => ['dev/private/database-backups/../user/profile-images/1/avatar.jpg'];
        yield 'nested path' => ['dev/private/database-backups/nested/backup-20260923T033045Z.sql'];
        yield 'wrong filename' => ['dev/private/database-backups/notes.txt'];
        yield 'absolute key' => ['/dev/private/database-backups/backup-20260923T033045Z.sql'];
        yield 'other storage prefix' => ['prod/private/database-backups/backup-20260923T033045Z.sql'];
    }

    #[DataProvider('provideRejectedObjectKeys')]
    public function testRejectsObjectKeysOutsideBackupPrefix(string $objectKey): void
    {
        $builder = new DatabaseBackupObjectKeyBuilder('dev');

        self::assertFalse($builder->isAllowedKey($objectKey));
    }
}
