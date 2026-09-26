<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

interface DatabaseBackupExporterInterface
{
    public function exportToFile(string $outputPath): void;
}
