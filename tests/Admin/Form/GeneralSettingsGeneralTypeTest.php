<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\GeneralSettingsGeneralType;
use App\Entity\GeneralSettings;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function file_put_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[Group('integration')]
final class GeneralSettingsGeneralTypeTest extends KernelTestCase
{
    private string $uploadedPath = '';

    protected function tearDown(): void
    {
        if ('' !== $this->uploadedPath && is_file($this->uploadedPath)) {
            unlink($this->uploadedPath);
        }

        parent::tearDown();
    }

    public function testAcceptsEmptyOptionalBrandingFields(): void
    {
        $settings = GeneralSettings::createWithDefaults()
            ->setPlatformLogo('dev/private/settings/branding/platform-logo/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.svg');
        $form = $this->createForm($settings);

        $form->submit([
            'platformName' => '  Pokédex  ',
            'platformSlogan' => ' ',
            'contactSupportEmail' => '',
            'showHiddenUsers' => '1',
            'save' => '',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame('Pokédex', $settings->getPlatformName());
        self::assertNull($settings->getPlatformSlogan());
        self::assertNull($settings->getContactSupportEmail());
        self::assertSame(
            'dev/private/settings/branding/platform-logo/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.svg',
            $settings->getPlatformLogo(),
        );
        self::assertTrue($settings->isShowHiddenUsers());
    }

    public function testNormalizesSupportEmail(): void
    {
        $settings = GeneralSettings::createWithDefaults();
        $form = $this->createForm($settings);

        $form->submit([
            'platformName' => '',
            'platformSlogan' => 'Atrápalos ya',
            'contactSupportEmail' => '  Support@Example.com  ',
            'showHiddenUsers' => '1',
            'save' => '',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertNull($settings->getPlatformName());
        self::assertSame('Atrápalos ya', $settings->getPlatformSlogan());
        self::assertSame('support@example.com', $settings->getContactSupportEmail());
    }

    public function testRejectsInvalidSupportEmail(): void
    {
        $settings = GeneralSettings::createWithDefaults();
        $form = $this->createForm($settings);

        $form->submit([
            'platformName' => 'Pokédex',
            'platformSlogan' => '',
            'contactSupportEmail' => 'no-es-un-correo',
            'showHiddenUsers' => '1',
            'save' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('contactSupportEmail')->getErrors(true)->count());
    }

    public function testRejectsPlatformNameThatExceedsMaxLength(): void
    {
        $settings = GeneralSettings::createWithDefaults();
        $form = $this->createForm($settings);

        $form->submit([
            'platformName' => str_repeat('a', GeneralSettings::PLATFORM_NAME_MAX_LENGTH + 1),
            'platformSlogan' => '',
            'contactSupportEmail' => '',
            'showHiddenUsers' => '1',
            'save' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('platformName')->getErrors(true)->count());
    }

    public function testRejectsNonSvgUpload(): void
    {
        $settings = GeneralSettings::createWithDefaults();
        $form = $this->createForm($settings);

        $form->submit([
            'platformName' => '',
            'platformSlogan' => '',
            'platformLogo' => $this->createUploadedFile('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'contactSupportEmail' => '',
            'showHiddenUsers' => '1',
            'save' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('platformLogo')->getErrors(true)->count());
        self::assertNull($settings->getPlatformLogo());
    }

    /**
     * @return FormInterface<GeneralSettings>
     */
    private function createForm(GeneralSettings $settings): FormInterface
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        return $formFactory->create(GeneralSettingsGeneralType::class, $settings, [
            'csrf_protection' => false,
        ]);
    }

    private function createUploadedFile(string $originalName, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'branding-form-');
        self::assertNotFalse($path);
        self::assertNotFalse(file_put_contents($path, $contents));
        $this->uploadedPath = $path;

        return new UploadedFile($path, $originalName, 'image/png', test: true);
    }
}
