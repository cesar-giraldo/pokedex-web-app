<?php

declare(strict_types=1);

namespace App\Web\Validator\Constraints;

use App\Web\Validator\DnsRecordLookup;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function filter_var;
use function idn_to_ascii;
use function is_string;
use function mb_strtolower;
use function rtrim;
use function str_contains;
use function strrpos;
use function substr;
use function trim;

use const DNS_A;
use const DNS_AAAA;
use const DNS_MX;
use const FILTER_VALIDATE_EMAIL;

final class DeliverableEmailAddressValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DnsRecordLookup $dns,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DeliverableEmailAddress) {
            throw new UnexpectedTypeException($constraint, DeliverableEmailAddress::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $email = mb_strtolower(trim($value));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $at = strrpos($email, '@');

        if (false === $at) {
            return;
        }

        $domain = substr($email, $at + 1);

        if ('' === $domain || str_contains($domain, ' ')) {
            return;
        }

        $ascii = idn_to_ascii($domain);

        if (false === $ascii || '' === $ascii) {
            $this->context->buildViolation($constraint->invalidMessage)->addViolation();

            return;
        }

        $mx = $this->dns->lookup($ascii, DNS_MX);

        if (false === $mx) {
            $this->context->buildViolation($constraint->retryMessage)->addViolation();

            return;
        }

        if ($this->hasDeliverableMx($mx)) {
            return;
        }

        if ($this->isNullMx($mx)) {
            $this->context->buildViolation($constraint->invalidMessage)->addViolation();

            return;
        }

        foreach ([DNS_A, DNS_AAAA] as $type) {
            $records = $this->dns->lookup($ascii, $type);

            if (false === $records) {
                $this->context->buildViolation($constraint->retryMessage)->addViolation();

                return;
            }

            if ([] !== $records) {
                return;
            }
        }

        $this->context->buildViolation($constraint->invalidMessage)->addViolation();
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function hasDeliverableMx(array $records): bool
    {
        foreach ($records as $record) {
            $target = $this->target($record);

            if ('' !== $target && '.' !== $target) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function isNullMx(array $records): bool
    {
        if ([] === $records) {
            return false;
        }

        foreach ($records as $record) {
            $target = $this->target($record);

            if ('.' !== $target && '' !== $target) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function target(array $record): string
    {
        $target = $record['target'] ?? '';

        if (!is_string($target)) {
            return '';
        }

        return mb_strtolower(rtrim($target, '.'));
    }
}
