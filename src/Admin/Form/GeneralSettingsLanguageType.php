<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

use function is_array;
use function is_string;

/**
 * @extends AbstractType<GeneralSettings>
 */
final class GeneralSettingsLanguageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $languageChoices = SupportedLanguage::choices();

        $builder
            ->add('enabledLanguages', ChoiceType::class, [
                'label' => false,
                'choices' => $languageChoices,
                'multiple' => true,
                'getter' => static fn (GeneralSettings $settings): array => $settings->getEnabledLanguages(),
                'setter' => static function (GeneralSettings $settings, mixed $languages): void {
                    if (!is_array($languages)) {
                        return;
                    }

                    $normalizedLanguages = [];

                    foreach ($languages as $language) {
                        if (!is_string($language)) {
                            continue;
                        }

                        if (null !== SupportedLanguage::tryFrom($language)) {
                            $normalizedLanguages[] = $language;
                        }
                    }

                    $settings->setEnabledLanguages($normalizedLanguages);
                },
                'constraints' => [
                    new Count(
                        min: 1,
                        minMessage: 'Debes seleccionar al menos un idioma.',
                    ),
                ],
            ])
            ->add('websiteDefaultLanguage', ChoiceType::class, [
                'label' => false,
                'choices' => $languageChoices,
                'placeholder' => 'Selecciona un idioma',
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un idioma por defecto.'),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Guardar Cambios',
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $settings = $event->getData();

            if (!$settings instanceof GeneralSettings) {
                return;
            }

            if (!$settings->isWebsiteDefaultLanguageEnabled()) {
                $event->getForm()->get('websiteDefaultLanguage')->addError(
                    new FormError('El idioma por defecto debe estar incluido en los idiomas habilitados.'),
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GeneralSettings::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'general_settings_language';
    }
}
