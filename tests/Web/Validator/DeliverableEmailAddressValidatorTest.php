<?php

declare(strict_types=1);

namespace App\Tests\Web\Validator;

use App\Web\Validator\Constraints\DeliverableEmailAddress;
use App\Web\Validator\Constraints\DeliverableEmailAddressValidator;
use App\Web\Validator\DnsRecordLookup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

use const DNS_A;
use const DNS_MX;

/**
 * @extends ConstraintValidatorTestCase<DeliverableEmailAddressValidator>
 */
#[CoversClass(DeliverableEmailAddressValidator::class)]
#[Group('unit')]
final class DeliverableEmailAddressValidatorTest extends ConstraintValidatorTestCase
{
    private DnsRecordLookup&MockObject $dns;

    public function testBlankValuesAreIgnored(): void
    {
        $this->validator->validate('', new DeliverableEmailAddress());
        $this->validator->validate(null, new DeliverableEmailAddress());

        $this->assertNoViolation();
    }

    public function testADomainWithMxIsAccepted(): void
    {
        $this->dns->method('lookup')->willReturnCallback(
            static fn (string $host, int $type): array => DNS_MX === $type
                ? [['target' => 'mail.example.com']]
                : [],
        );

        $this->validator->validate('ada@example.com', new DeliverableEmailAddress());

        $this->assertNoViolation();
    }

    public function testANullMxIsRejected(): void
    {
        $this->dns->method('lookup')->willReturn([['target' => '.']]);

        $this->validator->validate('ada@invalid.test', new DeliverableEmailAddress());

        $this->buildViolation('auth.email_domain')->assertRaised();
    }

    public function testAnAddressRecordIsAcceptedWhenThereIsNoMx(): void
    {
        $this->dns->method('lookup')->willReturnCallback(
            static fn (string $host, int $type): array => DNS_A === $type ? [['ip' => '127.0.0.1']] : [],
        );

        $this->validator->validate('ada@example.com', new DeliverableEmailAddress());

        $this->assertNoViolation();
    }

    public function testADomainWithoutMailRecordsIsRejected(): void
    {
        $this->dns->method('lookup')->willReturn([]);

        $this->validator->validate('ada@invalid.test', new DeliverableEmailAddress());

        $this->buildViolation('auth.email_domain')->assertRaised();
    }

    public function testADnsFailureAsksToRetry(): void
    {
        $this->dns->method('lookup')->willReturn(false);

        $this->validator->validate('ada@dns-down.test', new DeliverableEmailAddress());

        $this->buildViolation('auth.email_domain_retry')->assertRaised();
    }

    protected function createValidator(): DeliverableEmailAddressValidator
    {
        $this->dns = $this->createMock(DnsRecordLookup::class);

        return new DeliverableEmailAddressValidator($this->dns);
    }
}
