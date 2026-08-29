<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use App\Admin\Validator\Normalizer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function is_string;
use function preg_match;

final class NicknameFormatValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NicknameFormat) {
            throw new UnexpectedTypeException($constraint, NicknameFormat::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $normalized = Normalizer::trimNickname($value) ?? '';

        if ('' === $normalized) {
            return;
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $normalized)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
