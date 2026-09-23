<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\GdImageManagerFactory;
use App\Admin\Service\Storage\ImageVariant;
use App\Admin\Service\Storage\ImageVariantProcessor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function getimagesizefromstring;

#[CoversClass(ImageVariantProcessor::class)]
#[Group('unit')]
final class ImageVariantProcessorTest extends TestCase
{
    private ImageVariantProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ImageVariantProcessor(GdImageManagerFactory::create());
    }

    public function testCoverResizesToTargetSquare(): void
    {
        $webp = $this->processor->process($this->jpegBinary(800, 800), ImageVariant::Thumb);
        $info = getimagesizefromstring($webp);

        self::assertNotFalse($info);
        self::assertSame(400, $info[0]);
        self::assertSame(400, $info[1]);
        self::assertSame('image/webp', $info['mime']);
    }

    public function testScaleDownDoesNotEnlargeSmallImages(): void
    {
        $webp = $this->processor->process($this->jpegBinary(16, 16), ImageVariant::Display);
        $info = getimagesizefromstring($webp);

        self::assertNotFalse($info);
        self::assertSame(16, $info[0]);
        self::assertSame(16, $info[1]);
        self::assertSame('image/webp', $info['mime']);
    }

    public function testRejectsOriginalVariant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->processor->process($this->jpegBinary(16, 16), ImageVariant::Original);
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function jpegBinary(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'variant-jpeg-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagejpeg($image, $path, 90);

        $contents = file_get_contents($path);
        unlink($path);
        self::assertNotFalse($contents);

        return $contents;
    }
}
