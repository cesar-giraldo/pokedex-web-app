<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Validator\Normalizer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Form type for searching Pokemons (no mapped to any entity).
 *
 * @extends AbstractType<array<string, mixed>>
 */
class SearchPokemonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', SearchType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => 30,
                    'placeholder' => 'Buscar pokemons...',
                ],
                'constraints' => [
                    new Length(
                        max: 30,
                        maxMessage: 'La búsqueda no puede tener más de {{ limit }} caracteres.',
                        normalizer: Normalizer::trim(...),
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET', // Keep the form submission as GET for search functionality
            'csrf_protection' => false, // It is not needed for public search forms
        ]);
    }
}
