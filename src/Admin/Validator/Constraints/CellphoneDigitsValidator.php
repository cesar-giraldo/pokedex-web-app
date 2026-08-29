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

final class CellphoneDigitsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CellphoneDigits) {
            throw new UnexpectedTypeException($constraint, CellphoneDigits::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $trimmed = Normalizer::trim($value) ?? '';

        if ('' === $trimmed) {
            return;
        }

        if (!preg_match('/^\d+$/', $trimmed)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
