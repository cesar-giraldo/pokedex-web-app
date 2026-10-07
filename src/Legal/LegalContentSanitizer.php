<?php

declare(strict_types=1);

namespace App\Legal;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

use function trim;

final class LegalContentSanitizer
{
    public function __construct(
        private readonly HtmlSanitizerInterface $legalSanitizer,
    ) {
    }

    public function sanitize(string $html): string
    {
        return trim($this->legalSanitizer->sanitize($html));
    }
}
