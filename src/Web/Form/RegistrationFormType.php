<?php

declare(strict_types=1);

namespace App\Web\Form;

use App\Admin\Validator\Constraints\PasswordStrength;
use App\Web\Registration\RegistrationData;
use App\Web\Validator\Constraints\AvailableAccountIdentity;
use App\Web\Validator\Constraints\DeliverableEmailAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function is_array;
use function is_string;
use function mb_strtolower;
use function trim;

/**
 * @extends AbstractType<RegistrationData>
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordStrength = new PasswordStrength();
        $passwordStrength->message = 'auth.password_strength';

        $builder
            ->add('name', TextType::class, [
                'attr' => ['maxlength' => 100],
                'constraints' => [
                    new NotBlank(message: 'auth.name_required'),
                    new Length(max: 100, maxMessage: 'auth.name_max'),
                ],
            ])
            ->add('lastname', TextType::class, [
                'attr' => ['maxlength' => 100],
                'constraints' => [
                    new NotBlank(message: 'auth.lastname_required'),
                    new Length(max: 100, maxMessage: 'auth.lastname_max'),
                ],
            ])
            ->add('email', EmailType::class, [
                'attr' => ['maxlength' => 100],
                'constraints' => [
                    new NotBlank(message: 'auth.email_required'),
                    new Email(message: 'auth.email_invalid', mode: Email::VALIDATION_MODE_HTML5),
                    new Length(min: 5, max: 100, minMessage: 'auth.email_min', maxMessage: 'auth.email_max'),
                    new DeliverableEmailAddress(),
                    new AvailableAccountIdentity(),
                ],
            ])
            ->add('password', PasswordType::class, [
                'attr' => ['maxlength' => 128],
                'constraints' => [
                    new NotBlank(message: 'auth.password_required'),
                    $passwordStrength,
                    new Length(max: 128, maxMessage: 'auth.password_max'),
                ],
            ])
            ->add('passwordConfirm', PasswordType::class, [
                'attr' => ['maxlength' => 128],
                'constraints' => [
                    new NotBlank(message: 'auth.password_confirm_required'),
                    new Length(max: 128, maxMessage: 'auth.password_max'),
                ],
            ])
            ->add('companyFax', TextType::class, ['required' => false, 'empty_data' => '']);

        $this->addHiddenString($builder, 'timezone');
        $this->addHiddenString($builder, 'locale');
        $this->addHiddenString($builder, 'timeFormat');

        if (true === $options['require_legal_acceptance']) {
            $builder->add('terms', CheckboxType::class, [
                'required' => true,
                'constraints' => [
                    new IsTrue(message: 'auth.terms_required'),
                ],
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();

            if (!is_array($data)) {
                return;
            }

            foreach (['name', 'lastname', 'email', 'timezone', 'locale', 'timeFormat', 'companyFax'] as $field) {
                if (!isset($data[$field]) || !is_string($data[$field])) {
                    continue;
                }

                $data[$field] = trim($data[$field]);
            }

            if (isset($data['email']) && is_string($data['email'])) {
                $data['email'] = mb_strtolower($data['email']);
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationData::class,
            'require_legal_acceptance' => false,
            'attr' => [
                'data-turbo' => 'false',
                'data-controller' => 'registration-preferences',
            ],
            'constraints' => [
                new Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    if (!$data instanceof RegistrationData) {
                        return;
                    }

                    if ($data->password !== $data->passwordConfirm) {
                        $context->buildViolation('auth.password_mismatch')
                            ->atPath('passwordConfirm')
                            ->addViolation();
                    }

                    if ('' !== trim($data->companyFax)) {
                        $context->buildViolation('auth.register_rejected')->addViolation();
                    }
                }),
            ],
        ]);
        $resolver->setAllowedTypes('require_legal_acceptance', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'registration';
    }

    /**
     * @param FormBuilderInterface<RegistrationData|null> $builder
     */
    private function addHiddenString(FormBuilderInterface $builder, string $name): void
    {
        $builder->add($name, HiddenType::class, [
            'required' => false,
            'empty_data' => '',
        ]);

        // HiddenType no trae el transformador de TextType: sin él, un valor vacío
        // se normaliza a null y revienta las propiedades string del DTO.
        $builder->get($name)->addViewTransformer(new CallbackTransformer(
            static fn (mixed $value): string => is_string($value) ? $value : '',
            static fn (mixed $value): string => is_string($value) ? $value : '',
        ));
    }
}
