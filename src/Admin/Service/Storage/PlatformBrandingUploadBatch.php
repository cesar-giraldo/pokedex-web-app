<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\GeneralSettings;

final class PlatformBrandingUploadBatch
{
    /**
     * @param list<StagedPlatformBrandingFile> $replacements
     */
    public function __construct(
        private readonly PlatformBrandingStorage $storage,
        private readonly GeneralSettings $settings,
        private array $replacements,
    ) {
    }

    public function abort(): void
    {
        foreach ($this->replacements as $replacement) {
            $replacement->asset->writePath($this->settings, $replacement->previousPath);
            $this->storage->tryDelete($replacement->newPath);
        }

        $this->replacements = [];
    }

    public function deleteReplaced(): void
    {
        foreach ($this->replacements as $replacement) {
            $previousPath = $replacement->previousPath;

            if (null === $previousPath || '' === $previousPath || $previousPath === $replacement->newPath) {
                continue;
            }

            $this->storage->tryDelete($previousPath);
        }

        $this->replacements = [];
    }
}
