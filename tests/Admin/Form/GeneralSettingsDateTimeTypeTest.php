<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\GeneralSettingsDateTimeType;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\GeneralSettings;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

#[Group('integration')]
final class GeneralSettingsDateTimeTypeTest extends KernelTestCase
{
    public function testAcceptsValidPlatformDateTimePreferences(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        $settings = GeneralSettings::createWithDefaults();
        $form = $this->createDateTimeForm($formFactory, $settings);

        $form->submit([
            'defaultTimezone' => 'Europe/Madrid',
            'defaultLocale' => SupportedLocale::SpanishSpain->value,
            'defaultTimeFormat' => TimeFormat::Hour24->value,
            'save' => '',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame('Europe/Madrid', $settings->getDefaultTimezone());
        self::assertSame(SupportedLocale::SpanishSpain, $settings->getDefaultLocale());
        self::assertSame(TimeFormat::Hour24, $settings->getDefaultTimeFormat());
    }

    public function testRejectsEmptyTimezone(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        $settings = GeneralSettings::createWithDefaults();
        $form = $this->createDateTimeForm($formFactory, $settings);

        $form->submit([
            'defaultTimezone' => '',
            'defaultLocale' => SupportedLocale::SpanishColombia->value,
            'defaultTimeFormat' => TimeFormat::Hour12->value,
            'save' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('defaultTimezone')->getErrors(true)->count());
    }

    /**
     * @return FormInterface<GeneralSettings>
     */
    private function createDateTimeForm(FormFactoryInterface $formFactory, GeneralSettings $settings): FormInterface
    {
        return $formFactory->create(GeneralSettingsDateTimeType::class, $settings, [
            'csrf_protection' => false,
        ]);
    }
}
