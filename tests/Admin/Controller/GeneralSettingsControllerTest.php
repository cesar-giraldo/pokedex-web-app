<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

use function sprintf;

#[Group('functional')]
final class GeneralSettingsControllerTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    private ?string $previousPlatformName = null;

    private ?string $previousPlatformSlogan = null;

    private ?string $previousContactSupportEmail = null;

    private bool $previousShowHiddenUsers = true;

    private bool $restoreGeneralSettings = false;

    protected function tearDown(): void
    {
        if ($this->restoreGeneralSettings && self::$booted) {
            $settings = $this->generalSettingsRepository()->findSingleton();

            if (null !== $settings) {
                $settings
                    ->setPlatformName($this->previousPlatformName)
                    ->setPlatformSlogan($this->previousPlatformSlogan)
                    ->setContactSupportEmail($this->previousContactSupportEmail)
                    ->setShowHiddenUsers($this->previousShowHiddenUsers);

                $entityManager = static::getContainer()->get(EntityManagerInterface::class);
                $entityManager->flush();
            }
        }

        parent::tearDown();
    }

    public function testDeveloperSeesBrandingFieldsOnGeneralSettings(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);

        $crawler = $client->request('GET', '/admin/settings/general');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="general_settings_general"][enctype="multipart/form-data"]');
        self::assertSelectorTextContains('body', 'Nombre de la plataforma');
        self::assertSelectorTextContains('body', 'Eslogan');
        self::assertSelectorTextContains('body', 'Logo (modo claro)');
        self::assertSelectorTextContains('body', 'Logo (modo oscuro)');
        self::assertSelectorTextContains('body', 'Icono del menú lateral');
        self::assertSelectorTextContains('body', 'Correo de soporte');
        self::assertSelectorExists('#general-settings-platform-logo[accept=".svg,image/svg+xml"]');
        self::assertSelectorExists('#general-settings-platform-logo-dark[accept=".svg,image/svg+xml"]');
        self::assertSelectorExists('#general-settings-platform-icon[accept=".svg,image/svg+xml"]');

        $settings = $this->generalSettingsRepository()->findSingleton();
        $this->assertLogoPreviewFrame($crawler, 'Logo (modo claro)', $settings?->getPlatformLogo(), 'bg-gray-50');
        $this->assertLogoPreviewFrame($crawler, 'Logo (modo oscuro)', $settings?->getPlatformLogoDark(), 'bg-gray-900');
    }

    public function testDeveloperCanSaveOptionalBrandingText(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->captureGeneralSettings();

        $crawler = $client->request('GET', '/admin/settings/general');
        $form = $crawler->filter('form[name="general_settings_general"]')->form([
            'general_settings_general[platformName]' => 'Plataforma de prueba',
            'general_settings_general[platformSlogan]' => 'Eslogan de prueba',
            'general_settings_general[contactSupportEmail]' => 'Soporte@Example.com',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/admin/settings/general');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Plataforma de prueba');
        self::assertSelectorTextContains('body', 'Eslogan de prueba');
        self::assertSelectorTextContains('body', 'soporte@example.com');

        $settings = $this->generalSettingsRepository()->findSingleton();
        self::assertNotNull($settings);
        self::assertSame('Plataforma de prueba', $settings->getPlatformName());
        self::assertSame('Eslogan de prueba', $settings->getPlatformSlogan());
        self::assertSame('soporte@example.com', $settings->getContactSupportEmail());
    }

    public function testInvalidSupportEmailStaysOnTheEditForm(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->captureGeneralSettings();

        $crawler = $client->request('GET', '/admin/settings/general');
        $form = $crawler->filter('form[name="general_settings_general"]')->form([
            'general_settings_general[contactSupportEmail]' => 'no-es-un-correo',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Introduce un correo electrónico válido.');

        $settings = $this->generalSettingsRepository()->findSingleton();
        self::assertNotNull($settings);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->refresh($settings);
        self::assertSame($this->previousContactSupportEmail, $settings->getContactSupportEmail());
    }

    public function testBrandingAssetRouteFollowsTheStoredLogo(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);

        $settings = $this->generalSettingsRepository()->findSingleton();
        $client->request('GET', '/admin/settings/general/branding/platform-logo');

        if (null === $settings?->getPlatformLogo()) {
            self::assertResponseStatusCodeSame(404);

            return;
        }

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'image/svg+xml');
        self::assertResponseHeaderSame('x-content-type-options', 'nosniff');
    }

    public function testOperatorCannotOpenGeneralSettings(): void
    {
        $client = static::createClient();
        $this->loginAsOperator($client);

        $client->request('GET', '/admin/settings/general');

        self::assertResponseStatusCodeSame(403);
    }

    public function testHeaderLogoFallsBackToTheStaticAssetUntilOneIsUploaded(): void
    {
        $client = static::createClient();
        $this->loginAsOperator($client);

        $crawler = $client->request('GET', '/admin/home');

        self::assertResponseIsSuccessful();

        $logos = $crawler->filter('header a.lg\\:hidden img');
        self::assertCount(2, $logos);

        $settings = $this->generalSettingsRepository()->findSingleton();
        $expectedTitle = $settings?->getPlatformName() ?? 'Home';
        self::assertSame($expectedTitle, $logos->eq(0)->attr('title'));
        self::assertSame($expectedTitle, $logos->eq(1)->attr('title'));

        $icon = $crawler->filter('aside img.logo-icon');
        self::assertCount(1, $icon);
        self::assertSame($expectedTitle, $icon->attr('title'));
        $iconSrc = (string) $icon->attr('src');

        if (null === $settings?->getPlatformIcon() || '' === $settings->getPlatformIcon()) {
            self::assertStringContainsString('images/logo/logo-icon.svg', $iconSrc);
        } else {
            self::assertStringContainsString('/admin/settings/general/branding/platform-icon?', $iconSrc);
        }

        $lightSrc = (string) $logos->eq(0)->attr('src');
        $darkSrc = (string) $logos->eq(1)->attr('src');

        if (null === $settings?->getPlatformLogo() || '' === $settings->getPlatformLogo()) {
            self::assertStringContainsString('images/logo/logo.svg', $lightSrc);
        } else {
            self::assertStringContainsString('/admin/settings/general/branding/platform-logo?', $lightSrc);
        }

        if (null === $settings?->getPlatformLogoDark() || '' === $settings->getPlatformLogoDark()) {
            self::assertStringContainsString('images/logo/logo-dark.svg', $darkSrc);
        } else {
            self::assertStringContainsString('/admin/settings/general/branding/platform-logo-dark?', $darkSrc);
        }

        $this->assertFaviconUsesUploadedIconOrTheStaticAsset($crawler, $settings);

        $contactBox = $crawler->filter('aside .sidebar-promo-box');
        $supportEmail = $settings?->getContactSupportEmail();
        if (null === $supportEmail || '' === $supportEmail) {
            self::assertCount(0, $contactBox);
        } else {
            self::assertCount(1, $contactBox);
            self::assertSame('mailto:' . $supportEmail, $contactBox->filter('a')->attr('href'));
        }
    }

    public function testLoginFaviconUsesUploadedIconOrTheStaticAsset(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        $settings = $this->generalSettingsRepository()->findSingleton();
        $this->assertFaviconUsesUploadedIconOrTheStaticAsset($crawler, $settings);

        $expectedSlogan = $settings?->getPlatformSlogan() ?? 'La guia online más completa de Pokemones';
        self::assertStringContainsString(
            $expectedSlogan,
            $crawler->filter('p.text-center.text-gray-400')->text(),
        );
    }

    public function testAnonymousCanRequestTheFaviconButNotTheHeaderLogos(): void
    {
        $client = static::createClient();
        $settings = $this->generalSettingsRepository()->findSingleton();

        $client->request('GET', '/admin/settings/general/branding/platform-icon');

        if (null === $settings?->getPlatformIcon()) {
            self::assertResponseStatusCodeSame(404);
        } else {
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('content-type', 'image/svg+xml');
        }

        $client->request('GET', '/admin/settings/general/branding/platform-logo');

        self::assertResponseRedirects('/admin/login');
    }

    public function testOperatorCanRequestTheHeaderLogo(): void
    {
        $client = static::createClient();
        $this->loginAsOperator($client);

        $settings = $this->generalSettingsRepository()->findSingleton();
        $client->request('GET', '/admin/settings/general/branding/platform-logo');

        if (null === $settings?->getPlatformLogo()) {
            self::assertResponseStatusCodeSame(404);

            return;
        }

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'image/svg+xml');
    }

    private function captureGeneralSettings(): void
    {
        $settings = $this->generalSettingsRepository()->getOrCreateSingleton();
        if (null === $settings->getId()) {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->flush();
        }

        $this->previousPlatformName = $settings->getPlatformName();
        $this->previousPlatformSlogan = $settings->getPlatformSlogan();
        $this->previousContactSupportEmail = $settings->getContactSupportEmail();
        $this->previousShowHiddenUsers = $settings->isShowHiddenUsers();
        $this->restoreGeneralSettings = true;
    }

    private function assertLogoPreviewFrame(Crawler $crawler, string $alt, ?string $objectKey, string $backgroundClass): void
    {
        if (null === $objectKey || '' === $objectKey) {
            return;
        }

        $logos = $crawler->filter(sprintf('img[alt="%s"]', $alt));
        self::assertGreaterThan(0, $logos->count());
        $logos->each(static function (Crawler $logo) use ($backgroundClass): void {
            self::assertStringContainsString($backgroundClass, (string) $logo->closest('span')->attr('class'));
        });
    }

    private function assertFaviconUsesUploadedIconOrTheStaticAsset(Crawler $crawler, ?GeneralSettings $settings): void
    {
        $href = (string) $crawler->filter('link[rel="icon"]')->attr('href');

        if (null === $settings?->getPlatformIcon() || '' === $settings->getPlatformIcon()) {
            self::assertStringContainsString('images/logo/logo-icon.svg', $href);

            return;
        }

        self::assertStringContainsString('/admin/settings/general/branding/platform-icon?', $href);
    }

    private function generalSettingsRepository(): GeneralSettingsRepository
    {
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        self::assertInstanceOf(GeneralSettingsRepository::class, $repository);

        return $repository;
    }
}
