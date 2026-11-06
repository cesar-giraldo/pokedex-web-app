<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use App\Admin\Service\SvgSanitizer;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function file_get_contents;
use function is_string;
use function pathinfo;
use function strtolower;

use const PATHINFO_EXTENSION;

final class SvgFileValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SvgSanitizer $svgSanitizer = new SvgSanitizer(),
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SvgFile) {
            throw new UnexpectedTypeException($constraint, SvgFile::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof UploadedFile) {
            throw new UnexpectedValueException($value, UploadedFile::class);
        }

        $extension = strtolower((string) pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION));

        if ('svg' !== $extension) {
            $this->context->buildViolation($constraint->extensionMessage)->addViolation();

            return;
        }

        $size = $value->getSize();

        if (false === $size || $size > SvgFile::MAX_BYTES) {
            $this->context->buildViolation($constraint->maxSizeMessage)->addViolation();

            return;
        }

        if (!$this->isSafeSvg($value)) {
            $this->context->buildViolation($constraint->invalidMessage)->addViolation();
        }
    }

    private function isSafeSvg(UploadedFile $file): bool
    {
        $contents = file_get_contents($file->getPathname());

        if (!is_string($contents)) {
            return false;
        }

        return null !== $this->svgSanitizer->sanitize($contents);
    }
}
