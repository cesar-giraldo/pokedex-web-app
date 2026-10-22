<?php

declare(strict_types=1);

namespace App\Web\Security;

interface GoogleIdTokenVerifier
{
    public function verify(string $idToken): GoogleIdentity;
}
