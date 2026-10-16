<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Validator\Normalizer;
use App\Entity\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Form type for searching users in the admin listing (not mapped to an entity).
 *
 * @extends AbstractType<array<string, mixed>>
 */
class SearchUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $roleChoices = [];
        foreach (UserRole::cases() as $role) {
            $roleChoices[$role->label()] = $role->value;
        }

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
                        normalizer: Normalizer::trim(...),
                    ),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'multiple' => true,
                'choices' => $roleChoices,
                'empty_data' => [],
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
