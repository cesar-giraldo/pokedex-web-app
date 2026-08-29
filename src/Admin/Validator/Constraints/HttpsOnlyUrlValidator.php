<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use App\Admin\Validator\Normalizer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function filter_var;
use function is_string;
use function parse_url;
use function preg_match;
use function strtolower;

use const FILTER_VALIDATE_URL;
use const PHP_URL_SCHEME;

final class HttpsOnlyUrlValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof HttpsOnlyUrl) {
            throw new UnexpectedTypeException($constraint, HttpsOnlyUrl::class);
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

        if (preg_match('/^\s*javascript:/i', $trimmed) || preg_match('/^\s*data:/i', $trimmed)) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        if (!is_string($scheme) || 'https' !== strtolower($scheme)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
