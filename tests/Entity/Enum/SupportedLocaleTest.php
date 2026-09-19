<?php

declare(strict_types=1);

namespace App\Tests\Entity\Enum;

use App\Entity\Enum\SupportedLocale;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function count;

#[Group('unit')]
final class SupportedLocaleTest extends TestCase
{
    public function testDefaultColombiaLocale(): void
    {
        self::assertSame('es-CO', SupportedLocale::SpanishColombia->value);
        self::assertSame('es_CO', SupportedLocale::SpanishColombia->intlLocale());
        self::assertSame('Español (Colombia) - es-CO', SupportedLocale::SpanishColombia->label());
    }

    public function testBuildsChoicesAndOptions(): void
    {
        $choices = SupportedLocale::choices();
        $options = SupportedLocale::options();

        self::assertSame('es-CO', $choices['Español (Colombia) - es-CO']);
        self::assertSame('Español (Colombia) - es-CO', $options['es-CO']);
        self::assertCount(count(SupportedLocale::cases()), $choices);
        self::assertCount(count(SupportedLocale::cases()), $options);
    }
}
