<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use App\Admin\Service\GeneralSettingsProvider;
use App\Admin\Service\Storage\PlatformBrandingAsset;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PlatformBrandingExtension extends AbstractExtension
{
    public function __construct(
        private readonly GeneralSettingsProvider $generalSettingsProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('platform_branding_url', $this->resolveUrl(...)),
            new TwigFunction('platform_name', $this->resolveName(...)),
            new TwigFunction('platform_slogan', $this->resolveSlogan(...)),
            new TwigFunction('platform_support_email', $this->resolveSupportEmail(...)),
        ];
    }

    public function resolveName(): ?string
    {
        return $this->generalSettingsProvider->get()->getPlatformName();
    }

    public function resolveSlogan(): ?string
    {
        return $this->generalSettingsProvider->get()->getPlatformSlogan();
    }

    public function resolveSupportEmail(): ?string
    {
        return $this->generalSettingsProvider->get()->getContactSupportEmail();
    }

    public function resolveUrl(string $asset): ?string
    {
        $brandingAsset = PlatformBrandingAsset::tryFrom($asset);

        if (!$brandingAsset instanceof PlatformBrandingAsset) {
            return null;
        }

        $settings = $this->generalSettingsProvider->get();
        $objectKey = $brandingAsset->readPath($settings);

        if (null === $objectKey || '' === $objectKey) {
            return null;
        }

        return $this->urlGenerator->generate('app_backend_general_settings_branding_asset', [
            'asset' => $brandingAsset->value,
        ]) . '?v=' . $settings->getLastUpdatedAt()->getTimestamp();
    }
}
