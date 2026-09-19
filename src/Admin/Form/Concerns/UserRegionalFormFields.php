<?php

declare(strict_types=1);

namespace App\Admin\Form\Concerns;

use App\Admin\Data\IanaTimezones;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Timezone;

trait UserRegionalFormFields
{
    /**
     * @param FormBuilderInterface<User|null> $builder
     */
    private function addRegionalFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('timezone', ChoiceType::class, [
                'label' => false,
                'choices' => IanaTimezones::choices(),
                'placeholder' => 'Seleccione una zona horaria',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar una zona horaria.'),
                    new Timezone(message: 'La zona horaria no es válida.'),
                ],
            ])
            ->add('locale', EnumType::class, [
                'class' => SupportedLocale::class,
                'label' => false,
                'choice_label' => static fn (SupportedLocale $locale): string => $locale->label(),
                'placeholder' => 'Seleccione un locale',
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un locale.'),
                ],
            ])
            ->add('timeFormat', EnumType::class, [
                'class' => TimeFormat::class,
                'label' => false,
                'choice_label' => static fn (TimeFormat $format): string => $format->label(),
                'constraints' => [
                    new NotBlank(message: 'Debes seleccionar un formato de hora.'),
                ],
            ]);
    }
}
