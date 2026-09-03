<?php

declare(strict_types=1);

namespace App\Tests\Admin\Validator\Constraints;

use App\Admin\Validator\Constraints\PasswordStrength;
use App\Admin\Validator\Constraints\PasswordStrengthValidator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<PasswordStrengthValidator>
 */
#[Group('unit')]
final class PasswordStrengthValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PasswordStrengthValidator
    {
        return new PasswordStrengthValidator();
    }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validator->validate(null, new PasswordStrength());
        $this->assertNoViolation();

        $this->validator->validate('', new PasswordStrength());
        $this->assertNoViolation();
    }

    public function testValidPassword(): void
    {
        $this->validator->validate('abc12', new PasswordStrength());
        $this->assertNoViolation();
    }

    public function testTooShortPasswordIsInvalid(): void
    {
        $this->validator->validate('ab1', new PasswordStrength());
        $this->buildViolation('La contraseña debe tener al menos 5 caracteres e incluir letras y números.')
            ->assertRaised();
    }

    public function testPasswordWithoutNumbersIsInvalid(): void
    {
        $this->validator->validate('abcdef', new PasswordStrength());
        $this->buildViolation('La contraseña debe tener al menos 5 caracteres e incluir letras y números.')
            ->assertRaised();
    }

    public function testPasswordWithoutLettersIsInvalid(): void
    {
        $this->validator->validate('12345', new PasswordStrength());
        $this->buildViolation('La contraseña debe tener al menos 5 caracteres e incluir letras y números.')
            ->assertRaised();
    }
}
