<?php

declare(strict_types=1);

namespace App\Web\Registration;

enum ConfirmationAttempt: string
{
    case Confirmed = 'confirmed';
    case Mismatch = 'mismatch';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Missing = 'missing';
    case Resent = 'resent';
    case Cooldown = 'cooldown';
    case Limited = 'limited';
    case MailFailed = 'mail_failed';
}
