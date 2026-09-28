<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\GeneralSettings;

enum PlatformBrandingAsset: string
{
    case Logo = 'platform-logo';
    case LogoDark = 'platform-logo-dark';
    case Icon = 'platform-icon';
    case AuthLogo = 'platform-auth-logo';

    public const string ROUTE_REQUIREMENT = 'platform-logo-dark|platform-auth-logo|platform-logo|platform-icon';

    public function formField(): string
    {
        return match ($this) {
            self::Logo => 'platformLogo',
            self::LogoDark => 'platformLogoDark',
            self::Icon => 'platformIcon',
            self::AuthLogo => 'platformAuthLogo',
        };
    }

    public function readPath(GeneralSettings $settings): ?string
    {
        return match ($this) {
            self::Logo => $settings->getPlatformLogo(),
            self::LogoDark => $settings->getPlatformLogoDark(),
            self::Icon => $settings->getPlatformIcon(),
            self::AuthLogo => $settings->getPlatformAuthLogo(),
        };
    }

    public function writePath(GeneralSettings $settings, ?string $path): void
    {
        match ($this) {
            self::Logo => $settings->setPlatformLogo($path),
            self::LogoDark => $settings->setPlatformLogoDark($path),
            self::Icon => $settings->setPlatformIcon($path),
            self::AuthLogo => $settings->setPlatformAuthLogo($path),
        };
    }
}
