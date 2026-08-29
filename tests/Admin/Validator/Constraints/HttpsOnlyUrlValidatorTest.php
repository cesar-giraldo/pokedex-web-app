<?php

declare(strict_types=1);

namespace App\Tests\Admin\Validator\Constraints;

use App\Admin\Validator\Constraints\HttpsOnlyUrl;
use App\Admin\Validator\Constraints\HttpsOnlyUrlValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<HttpsOnlyUrlValidator>
 */
final class HttpsOnlyUrlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): HttpsOnlyUrlValidator
    {
        return new HttpsOnlyUrlValidator();
    }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validator->validate(null, new HttpsOnlyUrl());
        $this->assertNoViolation();

        $this->validator->validate('', new HttpsOnlyUrl());
        $this->assertNoViolation();
    }

    public function testValidHttpsUrl(): void
    {
        $this->validator->validate('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/1.png', new HttpsOnlyUrl());
        $this->assertNoViolation();
    }

    public function testHttpUrlIsInvalid(): void
    {
        $this->validator->validate('http://example.com/sprite.png', new HttpsOnlyUrl());
        $this->buildViolation('Introduce una URL válida que comience con https://')
            ->assertRaised();
    }

    public function testJavascriptUrlIsInvalid(): void
    {
        $this->validator->validate('javascript:alert(1)', new HttpsOnlyUrl());
        $this->buildViolation('Introduce una URL válida que comience con https://')
            ->assertRaised();
    }
}
