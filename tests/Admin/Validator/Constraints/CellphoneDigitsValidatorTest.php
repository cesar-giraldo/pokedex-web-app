<?php

declare(strict_types=1);

namespace App\Tests\Admin\Validator\Constraints;

use App\Admin\Validator\Constraints\CellphoneDigits;
use App\Admin\Validator\Constraints\CellphoneDigitsValidator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<CellphoneDigitsValidator>
 */
#[Group('unit')]
final class CellphoneDigitsValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CellphoneDigitsValidator
    {
        return new CellphoneDigitsValidator();
    }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validator->validate(null, new CellphoneDigits());
        $this->assertNoViolation();

        $this->validator->validate('', new CellphoneDigits());
        $this->assertNoViolation();
    }

    public function testValidCellphone(): void
    {
        $this->validator->validate('3001234567', new CellphoneDigits());
        $this->assertNoViolation();
    }

    public function testCellphoneWithLettersIsInvalid(): void
    {
        $this->validator->validate('30012345ab', new CellphoneDigits());
        $this->buildViolation('El celular solo puede contener dígitos.')
            ->assertRaised();
    }
}
