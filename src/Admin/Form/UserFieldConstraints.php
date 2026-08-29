<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Validator\Constraints\CellphoneDigits;
use App\Admin\Validator\Constraints\NicknameFormat;
use App\Admin\Validator\Normalizer;
use App\Entity\User;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;

use function is_array;
use function is_string;

/**
 * Constraints compartidos para campos de usuario en formularios del admin.
 */
final class UserFieldConstraints
{
    /**
     * @return list<NotBlank|Length>
     */
    public static function name(): array
    {
        return [
            new NotBlank(message: 'Este campo es obligatorio.', normalizer: Normalizer::trim(...)),
            new Length(
                max: 50,
                maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                normalizer: Normalizer::trim(...),
            ),
        ];
    }

    /**
     * @return list<NotBlank|Length>
     */
    public static function lastname(): array
    {
        return [
            new NotBlank(message: 'Este campo es obligatorio.', normalizer: Normalizer::trim(...)),
            new Length(
                max: 70,
                maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                normalizer: Normalizer::trim(...),
            ),
        ];
    }

    /**
     * @return list<Optional>
     */
    public static function optionalEmail(): array
    {
        return [
            new Optional([
                new Email(message: 'Introduce un correo electrónico válido.', normalizer: Normalizer::trim(...)),
                new Length(
                    max: 100,
                    maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                    normalizer: Normalizer::trim(...),
                ),
            ]),
        ];
    }

    /**
     * @param FormBuilderInterface<User|null> $builder
     */
    public static function registerOptionalEmailNormalization(FormBuilderInterface $builder): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();

            if (!is_array($data)) {
                return;
            }

            if (isset($data['email']) && is_string($data['email']) && '' === trim($data['email'])) {
                $data['email'] = '';
                $event->setData($data);
            }
        });
    }

    /**
     * @return list<NotBlank|Email|Length>
     */
    public static function requiredEmail(): array
    {
        return [
            new NotBlank(message: 'Este campo es obligatorio.', normalizer: Normalizer::trim(...)),
            new Email(message: 'Introduce un correo electrónico válido.', normalizer: Normalizer::trim(...)),
            new Length(
                max: 100,
                maxMessage: 'Este campo no puede tener más de {{ limit }} caracteres.',
                normalizer: Normalizer::trim(...),
            ),
        ];
    }

    /**
     * @return list<NotBlank|NicknameFormat|Length>
     */
    public static function nickname(): array
    {
        return [
            new NotBlank(message: 'Este campo es obligatorio.', normalizer: Normalizer::trimNickname(...)),
            new NicknameFormat(),
            new Length(
                min: 5,
                max: 20,
                minMessage: 'El nickname debe tener al menos {{ limit }} caracteres.',
                maxMessage: 'El nickname no puede tener más de {{ limit }} caracteres.',
                normalizer: Normalizer::trimNickname(...),
            ),
        ];
    }

    /**
     * @return list<NotBlank|CellphoneDigits|Length>
     */
    public static function cellphone(): array
    {
        return [
            new NotBlank(message: 'Este campo es obligatorio.', normalizer: Normalizer::trim(...)),
            new CellphoneDigits(),
            new Length(
                min: 8,
                max: 12,
                minMessage: 'El celular debe tener al menos {{ limit }} caracteres.',
                maxMessage: 'El celular no puede tener más de {{ limit }} caracteres.',
                normalizer: Normalizer::trim(...),
            ),
        ];
    }

    /**
     * @return list<NotBlank|NicknameFormat|Length>
     */
    public static function loginNickname(): array
    {
        return [
            new NotBlank(message: 'El nickname es obligatorio.', normalizer: Normalizer::trimNickname(...)),
            new NicknameFormat(),
            new Length(
                min: 5,
                max: 20,
                minMessage: 'El nickname debe tener al menos {{ limit }} caracteres.',
                maxMessage: 'El nickname no puede tener más de {{ limit }} caracteres.',
                normalizer: Normalizer::trimNickname(...),
            ),
        ];
    }
}
