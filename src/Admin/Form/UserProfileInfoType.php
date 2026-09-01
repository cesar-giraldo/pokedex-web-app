<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Data\WorldCountryCodes;
use App\Admin\Form\Concerns\UserProfileImageFormFields;
use App\Admin\Form\Type\CountryCodeChoiceType;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
final class UserProfileInfoType extends AbstractType
{
    use UserProfileImageFormFields;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['maxlength' => 50],
                'constraints' => UserFieldConstraints::name(),
            ])
            ->add('lastname', TextType::class, [
                'label' => false,
                'attr' => ['maxlength' => 70],
                'constraints' => UserFieldConstraints::lastname(),
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => ['maxlength' => 100],
                'constraints' => UserFieldConstraints::requiredEmail(),
            ])
            ->add('nickname', TextType::class, [
                'label' => false,
                'attr' => ['maxlength' => 20],
                'constraints' => UserFieldConstraints::nickname(),
            ])
            ->add('countryCode', CountryCodeChoiceType::class, [
                'country_choice_key' => $options['country_choice_key'],
            ])
            ->add('cellphone', TextType::class, [
                'label' => false,
                'attr' => ['maxlength' => 12],
                'constraints' => UserFieldConstraints::cellphone(),
            ]);

        $this->addProfileImageFields($builder, true);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);

        WorldCountryCodes::configureChoiceKeyOption($resolver);
    }
}
