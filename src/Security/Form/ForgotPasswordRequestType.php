<?php

declare(strict_types=1);

namespace App\Security\Form;

use App\Admin\Validator\Normalizer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{identifier?: string}>
 */
final class ForgotPasswordRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('identifier', TextType::class, [
            'label' => false,
            'attr' => [
                'maxlength' => 180,
                'autocomplete' => 'username',
            ],
            'constraints' => [
                new NotBlank(
                    message: 'auth.forgot_password_identifier_required',
                    normalizer: Normalizer::trim(...),
                ),
                new Length(
                    max: 180,
                    maxMessage: 'auth.forgot_password_identifier_max',
                    normalizer: Normalizer::trim(...),
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'data-turbo' => 'false',
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'forgot_password';
    }
}
