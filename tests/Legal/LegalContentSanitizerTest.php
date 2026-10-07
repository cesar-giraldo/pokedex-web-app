<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Legal\LegalContentSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

#[CoversClass(LegalContentSanitizer::class)]
#[Group('unit')]
final class LegalContentSanitizerTest extends TestCase
{
    public function testSanitizeRemovesScriptsAndKeepsAllowedMarkup(): void
    {
        $sanitizer = new LegalContentSanitizer(new HtmlSanitizer(
            new HtmlSanitizerConfig()
                ->allowElement('p')
                ->allowElement('strong'),
        ));

        self::assertSame(
            '<p>Hola <strong>mundo</strong></p>',
            $sanitizer->sanitize('<p>Hola <strong>mundo</strong><script>alert(1)</script></p>'),
        );
    }
}
