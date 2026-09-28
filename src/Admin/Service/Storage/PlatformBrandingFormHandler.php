<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\GeneralSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PlatformBrandingFormHandler
{
    public function __construct(
        private readonly PlatformBrandingStorage $platformBrandingStorage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param FormInterface<mixed> $form
     */
    public function handleFromForm(GeneralSettings $settings, FormInterface $form): void
    {
        foreach (PlatformBrandingAsset::cases() as $asset) {
            if (!$form->has($asset->formField())) {
                continue;
            }

            $uploadedFile = $form->get($asset->formField())->getData();

            if (!$uploadedFile instanceof UploadedFile) {
                continue;
            }

            $this->replace($settings, $asset, $uploadedFile);
        }
    }

    public function replace(GeneralSettings $settings, PlatformBrandingAsset $asset, UploadedFile $uploadedFile): void
    {
        $previousPath = $asset->readPath($settings);
        $newPath = $this->platformBrandingStorage->allocateObjectKey($asset);

        $asset->writePath($settings, $newPath);
        $this->entityManager->flush();

        try {
            $this->platformBrandingStorage->write($newPath, $uploadedFile);
        } catch (PlatformBrandingUploadException $exception) {
            $asset->writePath($settings, $previousPath);
            $this->entityManager->flush();
            $this->platformBrandingStorage->tryDelete($newPath);

            throw $exception;
        }

        $this->platformBrandingStorage->tryDelete($previousPath);
    }
}
