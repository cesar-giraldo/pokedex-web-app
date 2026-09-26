<?php

declare(strict_types=1);

namespace App\Admin\Command\CronJobs;

use App\Admin\Service\DatabaseBackup\DatabaseBackupExporterInterface;
use App\Admin\Service\DatabaseBackup\DatabaseBackupExportException;
use App\Admin\Service\DatabaseBackup\DatabaseBackupFilenameFactory;
use App\Admin\Service\DatabaseBackup\DatabaseBackupObjectKeyBuilder;
use App\Admin\Service\DatabaseBackup\DatabaseBackupUploaderInterface;
use App\Repository\GeneralSettingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function bin2hex;
use function fclose;
use function file_exists;
use function flock;
use function fopen;
use function is_dir;
use function is_resource;
use function mkdir;
use function random_bytes;
use function sprintf;
use function unlink;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

#[AsCommand(
    name: 'app:generate-database-backup',
    description: 'Exporta la base de datos a un archivo SQL y lo almacena en S3.',
)]
final class GenerateDatabaseBackupCommand extends Command
{
    public function __construct(
        private readonly DatabaseBackupFilenameFactory $filenameFactory,
        private readonly DatabaseBackupObjectKeyBuilder $objectKeyBuilder,
        private readonly DatabaseBackupExporterInterface $exporter,
        private readonly DatabaseBackupUploaderInterface $uploader,
        private readonly GeneralSettingsRepository $generalSettingsRepository,
        #[Autowire('%kernel.cache_dir%')]
        private readonly string $cacheDir,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $lockHandle = $this->acquireLock();

        if (false === $lockHandle) {
            $io->error('Ya hay una generación de backup en curso.');

            return Command::FAILURE;
        }

        $localPath = $this->cacheDir . '/database-backup-' . bin2hex(random_bytes(8)) . '.sql';

        try {
            $generatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $filename = $this->filenameFactory->create($generatedAt);
            $objectKey = $this->objectKeyBuilder->build($filename);

            $io->info(sprintf('Generando backup %s', $filename));

            $this->exporter->exportToFile($localPath);
            $this->uploader->upload($objectKey, $localPath);

            try {
                $this->generalSettingsRepository->recordLastDatabaseBackup($objectKey, $generatedAt);
            } catch (Throwable $exception) {
                $this->logger->critical('Database backup uploaded but failed to record metadata.', [
                    'object_key' => $objectKey,
                    'error' => $exception->getMessage(),
                ]);
                $io->error(sprintf(
                    'El archivo se subió a S3 pero no se pudo actualizar la configuración general. Clave: %s',
                    $objectKey,
                ));

                return Command::FAILURE;
            }

            $io->success(sprintf('Backup almacenado en %s', $objectKey));

            return Command::SUCCESS;
        } catch (DatabaseBackupExportException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected database backup failure.', [
                'error' => $exception->getMessage(),
            ]);
            $io->error('No se pudo generar el backup de la base de datos.');

            return Command::FAILURE;
        } finally {
            if (file_exists($localPath)) {
                unlink($localPath);
            }

            $this->releaseLock($lockHandle);
        }
    }

    /**
     * @return resource|false
     */
    private function acquireLock()
    {
        $lockDirectory = $this->cacheDir . '/locks';

        if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0o777, true) && !is_dir($lockDirectory)) {
            throw new DatabaseBackupExportException('No se pudo crear el directorio de bloqueo del backup.');
        }

        $handle = fopen($lockDirectory . '/generate-database-backup.lock', 'c');

        if (false === $handle) {
            throw new DatabaseBackupExportException('No se pudo abrir el archivo de bloqueo del backup.');
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        return $handle;
    }

    /**
     * @param resource|false $handle
     */
    private function releaseLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
