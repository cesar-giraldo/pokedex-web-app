<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Form type for searching backend users (no mapped to any entity).
 *
 * @extends AbstractType<array<string, mixed>>
 */
class SearchUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', SearchType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => 30,
                    'placeholder' => 'Buscar usuarios...',
                ],
                'constraints' => [
                    new Length(
                        max: 30,
                        maxMessage: 'La búsqueda no puede tener más de {{ limit }} caracteres.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
