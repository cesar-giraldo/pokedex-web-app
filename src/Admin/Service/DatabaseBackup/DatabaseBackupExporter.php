<?php

declare(strict_types=1);

namespace App\Admin\Service\DatabaseBackup;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(DatabaseBackupExporterInterface::class)]
final class DatabaseBackupExporter implements DatabaseBackupExporterInterface
{
    public function __construct(
        #[Autowire('%env(DATABASE_ENGINE)%')]
        private readonly string $databaseEngine,
        #[Autowire('%env(resolve:DATABASE_URL)%')]
        private readonly string $databaseUrl,
        private readonly DatabaseBackupConnectionParser $connectionParser,
        private readonly DatabaseBackupDumpCommandFactory $dumpCommandFactory,
        private readonly DatabaseBackupProcessRunnerInterface $processRunner,
    ) {
    }

    public function exportToFile(string $outputPath): void
    {
        $connection = $this->connectionParser->parse($this->databaseEngine, $this->databaseUrl);
        $dump = $this->dumpCommandFactory->create($connection, $outputPath);

        $this->processRunner->run($dump['command'], $dump['env']);
        $this->dumpCommandFactory->assertDumpFileIsValid($outputPath);
    }
}
