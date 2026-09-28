<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\Storage\PlatformBrandingAsset;
use App\Admin\Service\Storage\PlatformBrandingStorage;
use App\Repository\GeneralSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use function fclose;
use function fopen;
use function stream_copy_to_stream;

#[Route('/admin')]
#[IsGranted('ROLE_DEVELOPER')]
final class PlatformBrandingAssetController extends AbstractController
{
    public function __construct(
        private readonly GeneralSettingsRepository $generalSettingsRepository,
        private readonly PlatformBrandingStorage $platformBrandingStorage,
    ) {
    }

    #[Route(
        '/settings/general/branding/{asset}',
        name: 'app_backend_general_settings_branding_asset',
        requirements: ['asset' => PlatformBrandingAsset::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function show(string $asset): Response
    {
        $brandingAsset = PlatformBrandingAsset::tryFrom($asset);

        if (!$brandingAsset instanceof PlatformBrandingAsset) {
            throw new NotFoundHttpException();
        }

        $settings = $this->generalSettingsRepository->findSingleton();
        $objectKey = null === $settings ? null : $brandingAsset->readPath($settings);

        if (null === $objectKey || '' === $objectKey || !$this->platformBrandingStorage->isAllowedKey($brandingAsset, $objectKey)) {
            throw new NotFoundHttpException();
        }

        try {
            $stream = $this->platformBrandingStorage->readStream($objectKey);
        } catch (Throwable) {
            throw new NotFoundHttpException();
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

        $response->headers->set('Content-Type', PlatformBrandingStorage::MIME_TYPE);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private');

        return $response;
    }
}
