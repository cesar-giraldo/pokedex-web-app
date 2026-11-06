<?php

declare(strict_types=1);

namespace App\Admin\Service;

use DOMDocument;
use DOMElement;
use DOMNode;

use function in_array;
use function is_string;
use function libxml_clear_errors;
use function libxml_set_external_entity_loader;
use function libxml_use_internal_errors;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;

use const LIBXML_NONET;

final class SvgSanitizer
{
    /**
     * @var list<string>
     */
    private const array DISALLOWED_ELEMENTS = [
        'script',
        'foreignobject',
        'iframe',
        'object',
        'embed',
        'link',
        'meta',
        'base',
        'style',
        'animate',
        'animatetransform',
        'animatemotion',
        'set',
        'handler',
        'listener',
        'use',
    ];

    public function sanitize(string $contents): ?string
    {
        $contents = trim($contents);

        if ('' === $contents) {
            return null;
        }

        $withoutDoctype = preg_replace('/<!DOCTYPE[^>]*>/i', '', $contents);
        $contents = is_string($withoutDoctype) ? trim($withoutDoctype) : $contents;

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

        if (true !== $loaded || !$root instanceof DOMElement) {
            return null;
        }

        if ('svg' !== strtolower((string) $root->localName)) {
            return null;
        }

        if ($document->doctype instanceof DOMNode) {
            $document->removeChild($document->doctype);
        }

        $this->sanitizeElement($root);

        $sanitized = $document->saveXML($root);

        if (!is_string($sanitized) || '' === trim($sanitized)) {
            return null;
        }

        return $sanitized;
    }

    private function sanitizeElement(DOMElement $element): void
    {
        $attributes = [];

        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attribute) {
                $attributes[] = $attribute;
            }
        }

        foreach ($attributes as $attribute) {
            $name = strtolower((string) $attribute->localName);
            $value = trim($attribute->value);

            if (str_starts_with($name, 'on') || !$this->isAllowedAttribute($name, $value)) {
                $element->removeAttributeNode($attribute);
            }
        }

        $children = [];

        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if (in_array(strtolower((string) $child->localName), self::DISALLOWED_ELEMENTS, true)) {
                $element->removeChild($child);

                continue;
            }

            $this->sanitizeElement($child);
        }
    }

    private function isAllowedAttribute(string $name, string $value): bool
    {
        if (in_array($name, ['href', 'src'], true)) {
            return $this->isSafeUrl($value);
        }

        return true;
    }

    private function isSafeUrl(string $value): bool
    {
        if ('' === $value) {
            return false;
        }

        if (str_starts_with($value, '#')) {
            return true;
        }

        return !str_contains($value, ':');
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
