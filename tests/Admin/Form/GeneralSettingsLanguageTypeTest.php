<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\GeneralSettingsLanguageType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

#[Group('integration')]
final class GeneralSettingsLanguageTypeTest extends KernelTestCase
{
    public function testRejectsDefaultLanguageOutsideEnabledLanguages(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        $settings = GeneralSettings::createWithDefaults();
        $form = $this->createLanguageForm($formFactory, $settings);

        $form->submit([
            'enabledLanguages' => [SupportedLanguage::English->value],
            'websiteDefaultLanguage' => SupportedLanguage::Spanish->value,
            'save' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('websiteDefaultLanguage')->getErrors(true)->count());
    }

    public function testAcceptsDefaultLanguageInsideEnabledLanguages(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        $settings = GeneralSettings::createWithDefaults();
        $form = $this->createLanguageForm($formFactory, $settings);

        $form->submit([
            'enabledLanguages' => [
                SupportedLanguage::Spanish->value,
                SupportedLanguage::English->value,
            ],
            'websiteDefaultLanguage' => SupportedLanguage::English->value,
            'save' => '',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame(
            [SupportedLanguage::Spanish->value, SupportedLanguage::English->value],
            $settings->getEnabledLanguages(),
        );
        self::assertSame(SupportedLanguage::English->value, $settings->getWebsiteDefaultLanguage());
    }

    /**
     * @return FormInterface<GeneralSettings>
     */
    private function createLanguageForm(FormFactoryInterface $formFactory, GeneralSettings $settings): FormInterface
    {
        return $formFactory->create(GeneralSettingsLanguageType::class, $settings, [
            'csrf_protection' => false,
        ]);
    }
}
