<?php

declare(strict_types=1);

namespace App\Security\Form;

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
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;

/**
 * @extends AbstractType<null>
 */
final class ResetPasswordType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordStrength = new PasswordStrength();
        $passwordStrength->message = 'auth.password_strength';

        $builder
            ->add('plainPassword', PasswordType::class, [
                'label' => false,
                'mapped' => false,
                'attr' => [
                    'maxlength' => 128,
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new NotBlank(message: 'auth.password_required'),
                    $passwordStrength,
                    new Length(max: 128, maxMessage: 'auth.password_max'),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => false,
                'mapped' => false,
                'attr' => [
                    'maxlength' => 128,
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new NotBlank(message: 'auth.password_confirm_required'),
                    new Length(max: 128, maxMessage: 'auth.password_max'),
                ],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();
            $plainPassword = is_string($plainPassword) ? $plainPassword : '';
            $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';

            if ('' === $plainPassword || '' === $confirmPassword) {
                return;
            }

            if ($plainPassword !== $confirmPassword) {
                $form->get('confirmPassword')->addError(new FormError(
                    $this->translator->trans('auth.password_mismatch', domain: 'validators'),
                ));

                return;
            }

            /** @var User $user */
            $user = $form->getConfig()->getOption('user');

            /** @var UserPasswordHasherInterface $passwordHasher */
            $passwordHasher = $form->getConfig()->getOption('password_hasher');

            if ($passwordHasher->isPasswordValid($user, $plainPassword)) {
                $form->get('plainPassword')->addError(new FormError(
                    $this->translator->trans('auth.password_same_as_current', domain: 'validators'),
                ));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'attr' => [
                'data-turbo' => 'false',
            ],
        ]);
        $resolver->setRequired(['user', 'password_hasher']);
        $resolver->setAllowedTypes('user', User::class);
        $resolver->setAllowedTypes('password_hasher', UserPasswordHasherInterface::class);
    }

    public function getBlockPrefix(): string
    {
        return 'reset_password';
    }
}
