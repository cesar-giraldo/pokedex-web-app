<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\GeneralSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<GeneralSettings>
 */
final class GeneralSettingsGeneralType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('showHiddenUsers', CheckboxType::class, [
                'label' => false,
                'required' => false,
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
        return 'general_settings_general';
    }
}
