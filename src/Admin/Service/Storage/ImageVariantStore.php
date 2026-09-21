<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class ImageVariantStore
{
    public function __construct(
        private readonly ObjectStorage $objectStorage,
        private readonly ImageVariantProcessor $processor,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param list<ImageVariant> $variants
     */
    public function generate(string $originalKey, string $binary, array $variants): void
    {
        foreach ($variants as $variant) {
            try {
                $this->objectStorage->write(
                    $variant->objectKey($originalKey),
                    $this->processor->process($binary, $variant),
                );
            } catch (Throwable $exception) {
                $this->logger->warning('Failed to generate image variant.', [
                    'object_key' => $originalKey,
                    'variant' => $variant->value,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return resource
     */
    public function readStream(string $originalKey, ImageVariant $variant)
    {
        if ($variant->isOriginal()) {
            return $this->objectStorage->readStream($originalKey);
        }

        $targetKey = $variant->objectKey($originalKey);
        if (!$this->objectStorage->fileExists($targetKey)) {
            $this->generate($originalKey, $this->objectStorage->read($originalKey), [$variant]);
        }

        return $this->objectStorage->readStream($targetKey);
    }

    public function resolveMimeType(string $originalKey, ImageVariant $variant): string
    {
        return $this->objectStorage->resolveMimeType($variant->objectKey($originalKey));
    }

    public function deleteAll(?string $originalKey): void
    {
        if (null === $originalKey || '' === $originalKey) {
            return;
        }

        foreach (ImageVariant::derivedCases() as $variant) {
            $this->objectStorage->delete($variant->objectKey($originalKey));
        }

        $this->objectStorage->delete($originalKey);
    }

    public function tryDeleteAll(?string $originalKey): void
    {
        if (null === $originalKey || '' === $originalKey) {
            return;
        }

        foreach (ImageVariant::derivedCases() as $variant) {
            $this->objectStorage->tryDelete($variant->objectKey($originalKey));
        }

        $this->objectStorage->tryDelete($originalKey);
    }
}
