<?php

declare(strict_types=1);

namespace App\Tests\Web\Validator;

use App\Repository\UserRepository;
use App\Web\Validator\Constraints\AvailableAccountIdentity;
use App\Web\Validator\Constraints\AvailableAccountIdentityValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AvailableAccountIdentityValidator>
 */
#[CoversClass(AvailableAccountIdentityValidator::class)]
#[Group('unit')]
final class AvailableAccountIdentityValidatorTest extends ConstraintValidatorTestCase
{
    private MockObject&UserRepository $users;

    public function testBlankValuesAreIgnored(): void
    {
        $this->validator->validate('', new AvailableAccountIdentity());
        $this->validator->validate(null, new AvailableAccountIdentity());

        $this->assertNoViolation();
    }

    public function testAnExistingEmailGetsTheGenericMessage(): void
    {
        $this->users->expects(self::once())->method('existsByEmail')->with('ada@example.com')->willReturn(true);

        $this->validator->validate('Ada@Example.com', new AvailableAccountIdentity());

        $this->buildViolation('auth.register_unavailable')->assertRaised();
    }

    public function testAnExistingNicknameGetsTheSameMessage(): void
    {
        $this->users->method('existsByEmail')->willReturn(false);
        $this->users->expects(self::once())->method('existsByNickname')->with('ada@example.com')->willReturn(true);

        $this->validator->validate('ada@example.com', new AvailableAccountIdentity());

        $this->buildViolation('auth.register_unavailable')->assertRaised();
    }

    public function testAFreeIdentityIsAccepted(): void
    {
        $this->users->method('existsByEmail')->willReturn(false);
        $this->users->method('existsByNickname')->willReturn(false);

        $this->validator->validate('ada@example.com', new AvailableAccountIdentity());

        $this->assertNoViolation();
    }

    protected function createValidator(): AvailableAccountIdentityValidator
    {
        $this->users = $this->createMock(UserRepository::class);

        return new AvailableAccountIdentityValidator($this->users);
    }
}
