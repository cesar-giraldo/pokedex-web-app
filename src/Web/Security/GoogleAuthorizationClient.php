<?php

declare(strict_types=1);

namespace App\Web\Security;

interface GoogleAuthorizationClient
{
    public function isConfigured(): bool;

    public function authorizationUrl(string $state, string $redirectUri): string;

    public function fetchIdToken(string $code, string $redirectUri): string;
}
