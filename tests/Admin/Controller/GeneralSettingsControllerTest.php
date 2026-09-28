<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Repository\GeneralSettingsRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        $client->request('GET', '/admin/settings/general');

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

    private function generalSettingsRepository(): GeneralSettingsRepository
    {
        $repository = static::getContainer()->get(GeneralSettingsRepository::class);
        self::assertInstanceOf(GeneralSettingsRepository::class, $repository);

        return $repository;
    }
}
