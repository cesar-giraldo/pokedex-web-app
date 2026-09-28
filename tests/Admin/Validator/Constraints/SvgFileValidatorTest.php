<?php

declare(strict_types=1);

namespace App\Tests\Admin\Validator\Constraints;

use App\Admin\Validator\Constraints\SvgFile;
use App\Admin\Validator\Constraints\SvgFileValidator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

use function file_put_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * @extends ConstraintValidatorTestCase<SvgFileValidator>
 */
#[Group('unit')]
final class SvgFileValidatorTest extends ConstraintValidatorTestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        if ('' !== $this->path && is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    protected function createValidator(): SvgFileValidator
    {
        return new SvgFileValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new SvgFile());

        $this->assertNoViolation();
    }

    public function testRejectsNonUploadedFile(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('logo.svg', new SvgFile());
    }

    public function testAcceptsSvgDocument(): void
    {
        $this->validator->validate($this->createFile('logo.svg', $this->svgDocument()), new SvgFile());

        $this->assertNoViolation();
    }

    public function testAcceptsSvgWithDoctype(): void
    {
        $contents = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>
            SVG;

        $this->validator->validate($this->createFile('logo.SVG', $contents), new SvgFile());

        $this->assertNoViolation();
    }

    public function testRejectsNonSvgExtension(): void
    {
        $this->validator->validate($this->createFile('logo.png', $this->svgDocument()), new SvgFile());

        $this->buildViolation('Solo se permiten imágenes SVG.')
            ->assertRaised();
    }

    public function testRejectsOversizedFile(): void
    {
        $this->validator->validate(
            $this->createFile('logo.svg', str_repeat('a', SvgFile::MAX_BYTES + 1)),
            new SvgFile(),
        );

        $this->buildViolation('La imagen no puede superar 1 MB.')
            ->assertRaised();
    }

    public function testRejectsFileWhoseRootIsNotSvg(): void
    {
        $this->validator->validate($this->createFile('logo.svg', '<html><svg></svg></html>'), new SvgFile());

        $this->buildViolation('El archivo SVG no es válido.')
            ->assertRaised();
    }

    private function svgDocument(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';
    }

    private function createFile(string $originalName, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'svg-file-');
        self::assertNotFalse($path);
        self::assertNotFalse(file_put_contents($path, $contents));
        $this->path = $path;

        return new UploadedFile($path, $originalName, 'image/svg+xml', test: true);
    }
}
