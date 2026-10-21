<?php

declare(strict_types=1);

namespace App\Web\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class DeliverableEmailAddress extends Constraint
{
    public string $invalidMessage = 'auth.email_domain';

    public string $retryMessage = 'auth.email_domain_retry';
}
