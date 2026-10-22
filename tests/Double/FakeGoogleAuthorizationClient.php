<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Web\Security\GoogleAuthorizationClient;

final class FakeGoogleAuthorizationClient implements GoogleAuthorizationClient
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://accounts.google.test/auth?state=' . rawurlencode($state);
    }

    public function fetchIdToken(string $code, string $redirectUri): string
    {
        return 'fake-id-token';
    }
}
