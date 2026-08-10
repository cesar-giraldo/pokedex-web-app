<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\Pokemon;
use App\Entity\PokemonType;
use App\Repository\PokemonTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Url;

/**
 * @extends AbstractType<Pokemon>
 */
final class PokemonEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $integerConstraints = [
            new NotBlank(message: 'Este campo es obligatorio.'),
            new Positive(message: 'Debe ser un número entero positivo.'),
        ];

        $builder
            ->add('height', IntegerType::class, [
                'label' => false,
                'setter' => static function (Pokemon $pokemon, ?int $value): void {
                    if (null !== $value) {
                        $pokemon->setHeight($value);
                    }
                },
                'constraints' => $integerConstraints,
            ])
            ->add('weight', IntegerType::class, [
                'label' => false,
                'setter' => static function (Pokemon $pokemon, ?int $value): void {
                    if (null !== $value) {
                        $pokemon->setWeight($value);
                    }
                },
                'constraints' => $integerConstraints,
            ])
            ->add('type', EntityType::class, [
                'class' => PokemonType::class,
                'choice_label' => 'name',
                'placeholder' => 'Seleccione un tipo',
                'label' => false,
                'required' => true,
                'query_builder' => static fn (PokemonTypeRepository $repository) => $repository
                    ->createQueryBuilder('t')
                    ->orderBy('t.name', 'ASC'),
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un tipo.'),
                ],
            ])
            ->add('spriteFront', UrlType::class, [
                'label' => false,
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [
                    new Url(message: 'Introduce una URL válida.'),
                ],
            ])
            ->add('spriteBack', UrlType::class, [
                'label' => false,
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [
                    new Url(message: 'Introduce una URL válida.'),
                ],
            ])
            ->add('attack', IntegerType::class, [
                'label' => false,
                'setter' => static function (Pokemon $pokemon, ?int $value): void {
                    if (null !== $value) {
                        $pokemon->setAttack($value);
                    }
                },
                'constraints' => $integerConstraints,
            ])
            ->add('defense', IntegerType::class, [
                'label' => false,
                'setter' => static function (Pokemon $pokemon, ?int $value): void {
                    if (null !== $value) {
                        $pokemon->setDefense($value);
                    }
                },
                'constraints' => $integerConstraints,
            ])
            ->add('speed', IntegerType::class, [
                'label' => false,
                'setter' => static function (Pokemon $pokemon, ?int $value): void {
                    if (null !== $value) {
                        $pokemon->setSpeed($value);
                    }
                },
                'constraints' => $integerConstraints,
            ])
            ->add('healthPoints', IntegerType::class, [
                'label' => false,
                'setter' => static function (Pokemon $pokemon, ?int $value): void {
                    if (null !== $value) {
                        $pokemon->setHealthPoints($value);
                    }
                },
                'constraints' => $integerConstraints,
            ])
            ->add('isHidden', CheckboxType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Pokemon::class,
        ]);
    }
}
