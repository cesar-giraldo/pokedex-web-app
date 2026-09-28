<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Extensions;

use App\Admin\Service\GeneralSettingsProvider;
use App\Admin\Twig\Extensions\PlatformBrandingExtension;
use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(PlatformBrandingExtension::class)]
#[Group('unit')]
final class PlatformBrandingExtensionTest extends TestCase
{
    public function testRegistersTwigFunction(): void
    {
        $extension = new PlatformBrandingExtension(
            new GeneralSettingsProvider($this->createStub(GeneralSettingsRepository::class)),
            $this->createStub(UrlGeneratorInterface::class),
        );
        $functions = $extension->getFunctions();

        self::assertCount(4, $functions);
        self::assertSame('platform_branding_url', $functions[0]->getName());
        self::assertSame('platform_name', $functions[1]->getName());
        self::assertSame('platform_slogan', $functions[2]->getName());
        self::assertSame('platform_support_email', $functions[3]->getName());
    }

    public function testResolveNameUsesPlatformNameOrNull(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $empty = $this->createExtension(GeneralSettings::createWithDefaults(), $urlGenerator);
        $named = $this->createExtension(
            GeneralSettings::createWithDefaults()->setPlatformName('  Pokédex  '),
            $urlGenerator,
        );

        self::assertNull($empty->resolveName());
        self::assertSame('Pokédex', $named->resolveName());
        self::assertNull($empty->resolveSlogan());
        self::assertNull($empty->resolveSupportEmail());
        self::assertSame(
            'soporte@example.com',
            $this->createExtension(
                GeneralSettings::createWithDefaults()->setContactSupportEmail('  Soporte@Example.com  '),
                $urlGenerator,
            )->resolveSupportEmail(),
        );
        self::assertSame(
            'Atrápalos ya',
            $this->createExtension(
                GeneralSettings::createWithDefaults()->setPlatformSlogan('  Atrápalos ya  '),
                $urlGenerator,
            )->resolveSlogan(),
        );
    }

    public function testResolveUrlReturnsNullWhenLogoIsMissing(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $extension = $this->createExtension(GeneralSettings::createWithDefaults(), $urlGenerator);

        self::assertNull($extension->resolveUrl('platform-logo'));
        self::assertNull($extension->resolveUrl('platform-logo-dark'));
        self::assertNull($extension->resolveUrl('unknown'));
    }

    public function testResolveUrlPointsAtTheStoredLogo(): void
    {
        $settings = GeneralSettings::createWithDefaults()
            ->setPlatformLogo('dev/private/settings/branding/platform-logo/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.svg')
            ->setLastUpdatedAt(new DateTime('2026-09-27 12:00:00'));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_backend_general_settings_branding_asset', ['asset' => 'platform-logo'])
            ->willReturn('/admin/settings/general/branding/platform-logo');

        $extension = $this->createExtension($settings, $urlGenerator);

        self::assertSame(
            '/admin/settings/general/branding/platform-logo?v=' . $settings->getLastUpdatedAt()->getTimestamp(),
            $extension->resolveUrl('platform-logo'),
        );
        self::assertNull($extension->resolveUrl('platform-logo-dark'));
    }

    private function createExtension(
        GeneralSettings $settings,
        UrlGeneratorInterface $urlGenerator,
    ): PlatformBrandingExtension {
        $repository = $this->createStub(GeneralSettingsRepository::class);
        $repository->method('findSingleton')->willReturn($settings);

        return new PlatformBrandingExtension(new GeneralSettingsProvider($repository), $urlGenerator);
    }
}
