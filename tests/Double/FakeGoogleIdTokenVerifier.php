<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Web\Security\GoogleIdentity;
use App\Web\Security\GoogleIdTokenVerifier;
use App\Web\Security\GoogleSignInException;

final class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifier
{
    public static ?GoogleIdentity $identity = null;

    public function verify(string $idToken): GoogleIdentity
    {
        return self::$identity ?? throw new GoogleSignInException('No Google identity was prepared.');
    }
}
