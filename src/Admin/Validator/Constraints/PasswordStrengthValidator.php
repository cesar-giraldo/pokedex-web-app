<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function is_string;
use function preg_match;
use function strlen;

final class PasswordStrengthValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordStrength) {
            throw new UnexpectedTypeException($constraint, PasswordStrength::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (strlen($value) < 5) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        if (!preg_match('/[a-zA-Z]/', $value) || !preg_match('/\d/', $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
