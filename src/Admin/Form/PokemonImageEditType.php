<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\PokemonImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @extends AbstractType<array{description?: string|null}>
 */
final class PokemonImageEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'empty_data' => '',
                'attr' => ['maxlength' => PokemonImage::DESCRIPTION_MAX_LENGTH],
                'constraints' => [
                    new Length(
                        max: PokemonImage::DESCRIPTION_MAX_LENGTH,
                        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
