<?php

declare(strict_types=1);

namespace App\Admin\Validator\Constraints;

use DOMDocument;
use DOMElement;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function file_get_contents;
use function is_string;
use function pathinfo;
use function strtolower;
use function trim;

use const LIBXML_NONET;
use const PATHINFO_EXTENSION;

final class SvgFileValidator extends ConstraintValidator
{
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

        if (!$this->hasSvgRoot($value)) {
            $this->context->buildViolation($constraint->invalidMessage)->addViolation();
        }
    }

    private function hasSvgRoot(UploadedFile $file): bool
    {
        $contents = file_get_contents($file->getPathname());

        if (!is_string($contents) || '' === trim($contents)) {
            return false;
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_set_external_entity_loader(self::disableExternalEntity(...));

        try {
            $document = new DOMDocument();
            $loaded = $document->loadXML($contents, LIBXML_NONET);
            $root = $document->documentElement;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
            libxml_set_external_entity_loader(null);
        }

        $localName = $root instanceof DOMElement ? $root->localName : null;

        return true === $loaded && is_string($localName) && 'svg' === strtolower($localName);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function disableExternalEntity(?string $publicId, ?string $systemId, array $context): null
    {
        unset($publicId, $systemId, $context);

        return null;
    }
}
