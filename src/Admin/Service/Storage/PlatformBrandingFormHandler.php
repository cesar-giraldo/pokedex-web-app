<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\GeneralSettings;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PlatformBrandingFormHandler
{
    public function __construct(
        private readonly PlatformBrandingStorage $platformBrandingStorage,
    ) {
    }

    /**
     * Sube los SVG y apunta la entidad a las claves nuevas.
     * El archivo anterior se conserva hasta que el llamador confirma el flush.
     *
     * @param FormInterface<mixed> $form
     */
    public function handleFromForm(GeneralSettings $settings, FormInterface $form): PlatformBrandingUploadBatch
    {
        $replacements = [];

        try {
            foreach (PlatformBrandingAsset::cases() as $asset) {
                if (!$form->has($asset->formField())) {
                    continue;
                }

                $uploadedFile = $form->get($asset->formField())->getData();

                if (!$uploadedFile instanceof UploadedFile) {
                    continue;
                }

                $replacements[] = $this->stage($settings, $asset, $uploadedFile);
            }
        } catch (PlatformBrandingUploadException $exception) {
            $this->batch($settings, $replacements)->abort();

            throw $exception;
        }

        return $this->batch($settings, $replacements);
    }

    private function stage(GeneralSettings $settings, PlatformBrandingAsset $asset, UploadedFile $uploadedFile): StagedPlatformBrandingFile
    {
        $previousPath = $asset->readPath($settings);
        $newPath = $this->platformBrandingStorage->allocateObjectKey($asset);

        try {
            $this->platformBrandingStorage->write($newPath, $uploadedFile);
        } catch (PlatformBrandingUploadException $exception) {
            $this->platformBrandingStorage->tryDelete($newPath);

            throw $exception;
        }

        $asset->writePath($settings, $newPath);

        return new StagedPlatformBrandingFile($asset, $previousPath, $newPath);
    }

    /**
     * @param list<StagedPlatformBrandingFile> $replacements
     */
    private function batch(GeneralSettings $settings, array $replacements): PlatformBrandingUploadBatch
    {
        return new PlatformBrandingUploadBatch($this->platformBrandingStorage, $settings, $replacements);
    }
}
