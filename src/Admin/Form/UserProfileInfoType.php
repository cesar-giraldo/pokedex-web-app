<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Data\AmericasCountryCodes;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
        $countryChoices = AmericasCountryCodes::formChoices();

        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                ],
            ])
            ->add('nickname', TextType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(min: 5, minMessage: 'El nickname debe tener al menos {{ limit }} caracteres.'),
                ],
            ])
            ->add('countryCode', ChoiceType::class, [
                'label' => false,
                'choices' => $countryChoices,
                'placeholder' => 'Seleccione un país',
                'choice_value' => static fn (?int $value): ?string => null === $value ? null : (string) $value,
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un código de país.'),
                ],
            ])
            ->add('cellphone', TextType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(min: 8, minMessage: 'El celular debe tener al menos {{ limit }} caracteres.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
