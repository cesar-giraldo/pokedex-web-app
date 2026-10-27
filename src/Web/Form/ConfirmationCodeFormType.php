<?php

declare(strict_types=1);

namespace App\Web\Form;

use App\Web\Registration\ConfirmationCodeData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function preg_match;

/**
 * @extends AbstractType<ConfirmationCodeData>
 */
final class ConfirmationCodeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        for ($position = 1; $position <= 6; ++$position) {
            $builder->add('digit' . $position, TextType::class, [
                'required' => false,
                'attr' => [
                    'inputmode' => 'numeric',
                    'maxlength' => 1,
                    'pattern' => '[0-9]*',
                    'autocomplete' => 1 === $position ? 'one-time-code' : 'off',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ConfirmationCodeData::class,
            'attr' => [
                'data-turbo' => 'false',
                'data-controller' => 'confirmation-code',
            ],
            'constraints' => [
                new Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    if (!$data instanceof ConfirmationCodeData) {
                        return;
                    }

                    if (1 !== preg_match('/^\d{6}$/', $data->code())) {
                        $context->buildViolation('auth.confirm_code_required')->addViolation();
                    }
                }),
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'confirmation_code';
    }
}
