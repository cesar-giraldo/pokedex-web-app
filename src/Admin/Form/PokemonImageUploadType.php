<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{image?: mixed, description?: string|null}>
 */
final class PokemonImageUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('image', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar una imagen.'),
                    ...ImageUploadConstraints::upload(),
                ],
            ])
            ->add('description', TextType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => ['maxlength' => 255],
                'constraints' => [
                    new Length(
                        max: 255,
                        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
