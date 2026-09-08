<?php

declare(strict_types=1);

namespace App\Tests\Entity\Enum;

use App\Entity\Enum\TimeFormat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TimeFormatTest extends TestCase
{
    public function testProvidesLabels(): void
    {
        self::assertSame('12 horas (1:00 p. m.)', TimeFormat::Hour12->label());
        self::assertSame('24 horas (13:00)', TimeFormat::Hour24->label());
        self::assertTrue(TimeFormat::Hour12->isHour12());
        self::assertFalse(TimeFormat::Hour24->isHour12());
    }

    public function testBuildsChoicesAndOptions(): void
    {
        $choices = TimeFormat::choices();
        $options = TimeFormat::options();

        self::assertSame('12h', $choices['12 horas (1:00 p. m.)']);
        self::assertSame('12 horas (1:00 p. m.)', $options['12h']);
        self::assertCount(2, $choices);
        self::assertCount(2, $options);
    }
}
