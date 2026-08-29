<?php

declare(strict_types=1);

namespace App\Admin\Form\Type;

use App\Admin\Data\WorldCountryCodes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CountryCodeChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        WorldCountryCodes::configureChoiceKeyOption($resolver);

        $resolver->setDefaults([
            'label' => false,
            'placeholder' => 'Seleccione un país',
            'choice_value' => static fn (?int $value): ?string => null === $value ? null : (string) $value,
            'constraints' => [
                new NotBlank(message: 'Debes seleccionar un código de país.'),
            ],
        ]);

        $resolver->setNormalizer('choices', static function (Options $options, mixed $value): array {
            return WorldCountryCodes::formChoices($options['country_choice_key']);
        });
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
