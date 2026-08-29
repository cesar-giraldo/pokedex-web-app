<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Validator\Constraints\PasswordStrength;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

use function is_string;

/**
 * @extends AbstractType<null>
 */
final class UserProfilePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => false,
                'mapped' => false,
                'attr' => ['maxlength' => 128],
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(
                        max: 128,
                        maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => false,
                'mapped' => false,
                'attr' => ['maxlength' => 128],
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new PasswordStrength(),
                    new Length(
                        max: 128,
                        maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => false,
                'mapped' => false,
                'attr' => ['maxlength' => 128],
                'constraints' => [
                    new NotBlank(message: 'Este campo es obligatorio.'),
                    new Length(
                        max: 128,
                        maxMessage: 'La contraseña no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();

            /** @var User $user */
            $user = $form->getConfig()->getOption('user');

            /** @var UserPasswordHasherInterface $passwordHasher */
            $passwordHasher = $form->getConfig()->getOption('password_hasher');

            $currentPassword = $form->get('currentPassword')->getData();
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            $currentPassword = is_string($currentPassword) ? $currentPassword : '';
            $plainPassword = is_string($plainPassword) ? $plainPassword : '';
            $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';

            if ('' !== $currentPassword && !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError('La contraseña actual no es correcta.'));

                return;
            }

            if ($plainPassword !== $confirmPassword) {
                $form->get('confirmPassword')->addError(new FormError('Las contraseñas no coinciden.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);

        $resolver->setRequired(['user', 'password_hasher']);
        $resolver->setAllowedTypes('user', User::class);
        $resolver->setAllowedTypes('password_hasher', UserPasswordHasherInterface::class);
    }
}
