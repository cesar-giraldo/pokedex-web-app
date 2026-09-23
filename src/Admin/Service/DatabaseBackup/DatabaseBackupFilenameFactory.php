<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

use function sprintf;

final class DatabaseBackupFilenameFactory
{
    public function create(DateTimeInterface $generatedAt): string
    {
        $utc = DateTimeImmutable::createFromInterface($generatedAt)
            ->setTimezone(new DateTimeZone('UTC'));

        return sprintf('backup-%s.sql', $utc->format('Ymd\\THis\\Z'));
    }
}
