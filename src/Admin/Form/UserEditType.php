<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Data\WorldCountryCodes;
use App\Admin\Form\Type\CountryCodeChoiceType;
use App\Admin\Validator\Constraints\PasswordStrength;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;

use function is_array;
use function is_string;

/**
 * @extends AbstractType<User>
 */
final class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<UserRole> $assignableRoles */
        $assignableRoles = $options['assignable_roles'];

        $roleChoices = [];
        foreach ($assignableRoles as $role) {
            $roleChoices[$role->label()] = $role->value;
        }

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
                'required' => false,
                'attr' => ['maxlength' => 100],
                'constraints' => [
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
            ])
            ->add('status', EnumType::class, [
                'class' => UserStatus::class,
                'label' => false,
                'choice_label' => static fn (UserStatus $status): string => $status->label(),
            ])
            ->add('applicationRoles', ChoiceType::class, [
                'label' => false,
                'choices' => $roleChoices,
                'multiple' => true,
                'getter' => static fn (User $user): array => array_map(
                    static fn (UserRole $role): string => $role->value,
                    $user->getApplicationRoles(),
                ),
                'setter' => static function (User $user, mixed $roles): void {
                    if (!is_array($roles)) {
                        return;
                    }

                    $enumRoles = [];
                    foreach ($roles as $role) {
                        if (!is_string($role)) {
                            continue;
                        }

                        $enumRole = UserRole::tryFrom($role);
                        if (null !== $enumRole) {
                            $enumRoles[] = $enumRole;
                        }
                    }

                    $user->setApplicationRoles($enumRoles);
                },
                'constraints' => [
                    new Count(min: 1, minMessage: 'Debes seleccionar al menos un rol.'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => ['maxlength' => 128],
                'constraints' => [
                    new Optional([
                        new PasswordStrength(),
                        new Length(
                            max: 128,
                            maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.',
                        ),
                    ]),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => ['maxlength' => 128],
                'constraints' => [
                    new Optional([
                        new Length(
                            max: 128,
                            maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.',
                        ),
                    ]),
                ],
            ]);

        if ($options['show_is_hidden']) {
            $builder->add('isHidden', CheckboxType::class, [
                'label' => false,
                'required' => false,
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            $plainPassword = is_string($plainPassword) ? $plainPassword : '';
            $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';

            if ('' === $plainPassword && '' === $confirmPassword) {
                return;
            }

            if ($plainPassword !== $confirmPassword) {
                if ('' === $plainPassword) {
                    $form->get('plainPassword')->addError(new FormError('Debes ingresar la nueva contraseña.'));

                    return;
                }

                if ('' === $confirmPassword) {
                    $form->get('confirmPassword')->addError(new FormError('Debes confirmar la contraseña.'));

                    return;
                }

                $form->get('confirmPassword')->addError(new FormError('Las contraseñas no coinciden.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'show_is_hidden' => false,
            'assignable_roles' => [],
        ]);

        WorldCountryCodes::configureChoiceKeyOption($resolver);

        $resolver->setAllowedTypes('show_is_hidden', 'bool');
        $resolver->setAllowedTypes('assignable_roles', 'array');
    }
}
