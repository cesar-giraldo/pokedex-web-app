<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\DatabaseBackup\DatabaseBackupObjectKeyBuilder;
use App\Admin\Service\Storage\ObjectStorage;
use App\Repository\GeneralSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use function basename;
use function fclose;
use function fopen;
use function stream_copy_to_stream;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class DatabaseBackupDownloadController extends AbstractController
{
    public function __construct(
        private readonly GeneralSettingsRepository $generalSettingsRepository,
        private readonly ObjectStorage $objectStorage,
        private readonly DatabaseBackupObjectKeyBuilder $objectKeyBuilder,
    ) {
    }

    #[Route('/database-backup/download', name: 'app_backend_database_backup_download', methods: ['GET'])]
    public function download(): Response
    {
        $settings = $this->generalSettingsRepository->findSingleton();
        $objectKey = $settings?->getLastBackupFilePath();

        if (null === $settings || !$settings->hasLastDatabaseBackup() || null === $objectKey || '' === $objectKey) {
            throw new NotFoundHttpException();
        }

        if (!$this->objectKeyBuilder->isAllowedKey($objectKey)) {
            throw new NotFoundHttpException();
        }

        try {
            $stream = $this->objectStorage->readStream($objectKey);
        } catch (Throwable) {
            throw new NotFoundHttpException();
        }

        $filename = basename($objectKey);
        if ('' === $filename) {
            $filename = 'backup.sql';
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            $output = fopen('php://output', 'w');

            if (false === $output) {
                fclose($stream);

                return;
            }

            stream_copy_to_stream($stream, $output);
            fclose($stream);
            fclose($output);
        });

        $response->headers->set('Content-Type', 'application/sql');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        );
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
