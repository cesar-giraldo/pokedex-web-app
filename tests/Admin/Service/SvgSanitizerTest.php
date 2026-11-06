<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Service\SvgSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(SvgSanitizer::class)]
#[Group('unit')]
final class SvgSanitizerTest extends TestCase
{
    public function testRemovesScriptAndEventHandlers(): void
    {
        $sanitizer = new SvgSanitizer();
        $contents = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" onclick="alert(1)">
                <script>alert(1)</script>
                <circle cx="5" cy="5" r="4"/>
            </svg>
            SVG;

        $sanitized = $sanitizer->sanitize($contents);

        self::assertNotNull($sanitized);
        self::assertStringContainsString('<circle', $sanitized);
        self::assertStringNotContainsString('script', $sanitized);
        self::assertStringNotContainsString('onclick', $sanitized);
        self::assertStringNotContainsString('alert', $sanitized);
    }

    public function testRejectsNonSvgRoot(): void
    {
        $sanitizer = new SvgSanitizer();

        self::assertNull($sanitizer->sanitize('<html><svg></svg></html>'));
    }

    public function testStripsExternalHref(): void
    {
        $sanitizer = new SvgSanitizer();
        $contents = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg">
                <image href="javascript:alert(1)"/>
                <image href="#ok"/>
            </svg>
            SVG;

        $sanitized = $sanitizer->sanitize($contents);

        self::assertNotNull($sanitized);
        self::assertStringNotContainsString('javascript', $sanitized);
        self::assertStringContainsString('href="#ok"', $sanitized);
    }
}
