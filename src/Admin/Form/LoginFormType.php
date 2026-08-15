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
                'constraints' => [
                    new NotBlank(message: 'El nickname es obligatorio.'),
                    new Length(
                        min: 5,
                        minMessage: 'El nickname debe tener al menos {{ limit }} caracteres.',
                    ),
                ],
                'attr' => [
                    'autocomplete' => 'username',
                    'placeholder' => 'tu-nickname',
                ],
            ])
            ->add('_password', PasswordType::class, [
                'label' => 'Contraseña',
                'constraints' => [
                    new NotBlank(message: 'La contraseña es obligatoria.'),
                ],
                'attr' => [
                    'autocomplete' => 'current-password',
                    'placeholder' => 'Ingresa tu contraseña',
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
