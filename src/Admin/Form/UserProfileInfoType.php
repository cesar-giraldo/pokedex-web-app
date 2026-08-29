<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Data\WorldCountryCodes;
use App\Admin\Form\Type\CountryCodeChoiceType;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
final class UserProfileInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['maxlength' => 50],
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(
                        max: 50,
                        maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => false,
                'attr' => ['maxlength' => 70],
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(
                        max: 70,
                        maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => ['maxlength' => 100],
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(
                        max: 100,
                        maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('nickname', TextType::class, [
                'label' => false,
                'attr' => ['maxlength' => 20],
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(
                        min: 5,
                        max: 20,
                        minMessage: 'El nickname debe tener al menos {{ limit }} caracteres.',
                        maxMessage: 'El nickname no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('countryCode', CountryCodeChoiceType::class, [
                'country_choice_key' => $options['country_choice_key'],
            ])
            ->add('cellphone', TextType::class, [
                'label' => false,
                'attr' => ['maxlength' => 12],
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(
                        min: 8,
                        max: 12,
                        minMessage: 'El celular debe tener al menos {{ limit }} caracteres.',
                        maxMessage: 'El celular no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);

        WorldCountryCodes::configureChoiceKeyOption($resolver);
    }
}
