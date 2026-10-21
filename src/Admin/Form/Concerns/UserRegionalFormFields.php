<?php

declare(strict_types=1);

namespace App\Admin\Form\Concerns;

use App\Admin\Data\IanaTimezones;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Timezone;

use function array_values;
use function in_array;
use function is_string;

trait UserRegionalFormFields
{
    private function configureRegionalFieldOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'enabled_languages' => array_map(
                static fn (SupportedLanguage $language): string => $language->value,
                SupportedLanguage::cases(),
            ),
            'website_default_language' => SupportedLanguage::Spanish->value,
        ]);
        $resolver->setAllowedTypes('enabled_languages', 'string[]');
        $resolver->setAllowedTypes('website_default_language', 'string');
    }

    /**
     * @param FormBuilderInterface<User|null> $builder
     * @param array<string, mixed>            $options
     */
    private function addRegionalFields(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $enabledLanguages */
        $enabledLanguages = $options['enabled_languages'];
        /** @var string $websiteDefaultLanguage */
        $websiteDefaultLanguage = $options['website_default_language'];
        $languageChoices = [];

        foreach (SupportedLanguage::cases() as $language) {
            if (in_array($language->value, $enabledLanguages, true)) {
                $languageChoices[$language->label()] = $language->value;
            }
        }

        $builder
            ->add('timezone', ChoiceType::class, [
                'label' => false,
                'choices' => IanaTimezones::choices(),
                'placeholder' => 'Seleccione una zona horaria',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar una zona horaria.'),
                    new Timezone(message: 'La zona horaria no es válida.'),
                ],
            ])
            ->add('preferredLanguage', ChoiceType::class, [
                'label' => false,
                'choices' => $languageChoices,
                'placeholder' => 'Seleccione un idioma',
                'getter' => static function (User $user) use ($enabledLanguages, $websiteDefaultLanguage): string {
                    $settings = GeneralSettings::createWithDefaults()
                        ->setEnabledLanguages($enabledLanguages)
                        ->setWebsiteDefaultLanguage($websiteDefaultLanguage);

                    return new LegalLanguageResolver()->preferredForUser($user, $settings)->value;
                },
                'setter' => static function (User $user, mixed $language): void {
                    if (!is_string($language)) {
                        return;
                    }

                    $preferredLanguage = SupportedLanguage::tryFrom($language);

                    if (null !== $preferredLanguage) {
                        $user->setPreferredLanguage($preferredLanguage);
                    }
                },
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un idioma.'),
                    new Choice(
                        choices: array_values($languageChoices),
                        message: 'El idioma debe estar habilitado en la plataforma.',
                    ),
                ],
            ])
            ->add('locale', EnumType::class, [
                'class' => SupportedLocale::class,
                'label' => false,
                'choice_label' => static fn (SupportedLocale $locale): string => $locale->label(),
                'placeholder' => 'Seleccione un locale',
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un locale.'),
                ],
            ])
            ->add('timeFormat', EnumType::class, [
                'class' => TimeFormat::class,
                'label' => false,
                'choice_label' => static fn (TimeFormat $format): string => $format->label(),
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un formato de hora.'),
                ],
            ]);
    }
}
