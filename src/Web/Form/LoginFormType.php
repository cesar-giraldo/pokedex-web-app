<?php

declare(strict_types=1);

namespace App\Web\Form;

use App\Admin\Validator\Normalizer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{_username?: string, _password?: string, _remember_me?: bool}>
 */
final class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', TextType::class, [
                'label' => false,
                'attr' => [
                    'maxlength' => 100,
                    'autocomplete' => 'username',
                ],
                'constraints' => [
                    new NotBlank(message: 'El nickname es obligatorio.', normalizer: Normalizer::trimNickname(...)),
                    new Length(
                        max: 100,
                        maxMessage: 'El nickname no puede tener más de {{ limit }} caracteres.',
                        normalizer: Normalizer::trimNickname(...),
                    ),
                ],
            ])
            ->add('_password', PasswordType::class, [
                'label' => false,
                'attr' => [
                    'maxlength' => 128,
                    'autocomplete' => 'current-password',
                ],
                'constraints' => [
                    new NotBlank(message: 'La contraseña es obligatoria.', normalizer: Normalizer::trim(...)),
                    new Length(
                        max: 128,
                        maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.',
                        normalizer: Normalizer::trim(...),
                    ),
                ],
            ])
            ->add('_remember_me', CheckboxType::class, [
                'label' => false,
                'required' => false,
                'value' => 'on',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'attr' => [
                'data-turbo' => 'false',
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
