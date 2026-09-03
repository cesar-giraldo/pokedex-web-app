<?php

declare(strict_types=1);

namespace App\Tests\Admin\Validator\Constraints;

use App\Admin\Validator\Constraints\NicknameFormat;
use App\Admin\Validator\Constraints\NicknameFormatValidator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<NicknameFormatValidator>
 */
#[Group('unit')]
final class NicknameFormatValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NicknameFormatValidator
    {
        return new NicknameFormatValidator();
    }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validator->validate(null, new NicknameFormat());
        $this->assertNoViolation();

        $this->validator->validate('', new NicknameFormat());
        $this->assertNoViolation();
    }

    public function testValidNickname(): void
    {
        $this->validator->validate('Test-User_1', new NicknameFormat());
        $this->assertNoViolation();
    }

    public function testNicknameWithSpacesIsInvalid(): void
    {
        $this->validator->validate('test user', new NicknameFormat());
        $this->buildViolation('El nickname solo puede contener letras minúsculas, números, guiones y guiones bajos.')
            ->assertRaised();
    }

    public function testNicknameWithInvalidCharactersIsInvalid(): void
    {
        $this->validator->validate('test@user', new NicknameFormat());
        $this->buildViolation('El nickname solo puede contener letras minúsculas, números, guiones y guiones bajos.')
            ->assertRaised();
    }
}
