<?php

declare(strict_types=1);

namespace App\Admin\Form;

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
                'label' => 'Nickname',
                'attr' => [
                    'maxlength' => 20,
                    'autocomplete' => 'username',
                    'placeholder' => 'tu-nickname',
                ],
                'constraints' => [
                    new NotBlank(message: 'El nickname es obligatorio.'),
                    new Length(
                        min: 5,
                        max: 20,
                        minMessage: 'El nickname debe tener al menos {{ limit }} caracteres.',
                        maxMessage: 'El nickname no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('_password', PasswordType::class, [
                'label' => 'Contraseña',
                'attr' => [
                    'maxlength' => 128,
                    'autocomplete' => 'current-password',
                    'placeholder' => 'Ingresa tu contraseña',
                ],
                'constraints' => [
                    new NotBlank(message: 'La contraseña es obligatoria.'),
                    new Length(
                        max: 128,
                        maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('_remember_me', CheckboxType::class, [
                'label' => 'Recordarme',
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
