<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Data\IanaTimezones;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\GeneralSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Timezone;

/**
 * @extends AbstractType<GeneralSettings>
 */
final class GeneralSettingsDateTimeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('defaultTimezone', ChoiceType::class, [
                'label' => false,
                'choices' => IanaTimezones::choices(),
                'placeholder' => 'Seleccione una zona horaria',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar una zona horaria por defecto.'),
                    new Timezone(message: 'La zona horaria no es válida.'),
                ],
            ])
            ->add('defaultLocale', EnumType::class, [
                'class' => SupportedLocale::class,
                'label' => false,
                'choice_label' => static fn (SupportedLocale $locale): string => $locale->label(),
                'placeholder' => 'Seleccione un locale',
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un locale por defecto.'),
                ],
            ])
            ->add('defaultTimeFormat', EnumType::class, [
                'class' => TimeFormat::class,
                'label' => false,
                'choice_label' => static fn (TimeFormat $format): string => $format->label(),
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un formato de hora por defecto.'),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Guardar Cambios',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GeneralSettings::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'general_settings_datetime';
    }
}
