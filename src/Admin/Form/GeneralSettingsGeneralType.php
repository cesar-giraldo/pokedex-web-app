<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Validator\Constraints\SvgFile;
use App\Admin\Validator\Normalizer;
use App\Entity\GeneralSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @extends AbstractType<GeneralSettings>
 */
final class GeneralSettingsGeneralType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('platformName', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['maxlength' => GeneralSettings::PLATFORM_NAME_MAX_LENGTH],
                'constraints' => [
                    new Length(
                        max: GeneralSettings::PLATFORM_NAME_MAX_LENGTH,
                        maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                        normalizer: Normalizer::trim(...),
                    ),
                ],
            ])
            ->add('platformSlogan', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['maxlength' => GeneralSettings::PLATFORM_SLOGAN_MAX_LENGTH],
                'constraints' => [
                    new Length(
                        max: GeneralSettings::PLATFORM_SLOGAN_MAX_LENGTH,
                        maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                        normalizer: Normalizer::trim(...),
                    ),
                ],
            ])
            ->add('platformLogo', FileType::class, $this->svgFileOptions())
            ->add('platformLogoDark', FileType::class, $this->svgFileOptions())
            ->add('platformIcon', FileType::class, $this->svgFileOptions())
            ->add('platformAuthLogo', FileType::class, $this->svgFileOptions())
            ->add('contactSupportEmail', EmailType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['maxlength' => GeneralSettings::CONTACT_SUPPORT_EMAIL_MAX_LENGTH],
                'constraints' => [
                    new Email(
                        message: 'Introduce un correo electrónico válido.',
                        normalizer: Normalizer::trim(...),
                    ),
                    new Length(
                        max: GeneralSettings::CONTACT_SUPPORT_EMAIL_MAX_LENGTH,
                        maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                        normalizer: Normalizer::trim(...),
                    ),
                ],
            ])
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

    /**
     * @return array<string, mixed>
     */
    private function svgFileOptions(): array
    {
        return [
            'label' => false,
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new SvgFile(),
            ],
        ];
    }
}
