<?php

declare(strict_types=1);

namespace App\Web\Validator\Constraints;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function is_string;
use function mb_strtolower;
use function trim;

final class AvailableAccountIdentityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableAccountIdentity) {
            throw new UnexpectedTypeException($constraint, AvailableAccountIdentity::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $email = mb_strtolower(trim($value));

        if ('' === $email) {
            return;
        }

        if ($this->users->existsByEmail($email) || $this->users->existsByNickname($email)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
