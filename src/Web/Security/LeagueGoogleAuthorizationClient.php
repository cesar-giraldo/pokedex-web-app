<?php

declare(strict_types=1);

namespace App\Web\Security;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Google;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function is_string;

final class LeagueGoogleAuthorizationClient implements GoogleAuthorizationClient
{
    public function __construct(
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_ID')]
        private readonly string $clientId,
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_SECRET')]
        private readonly string $clientSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $this->provider($redirectUri)->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile'],
            'state' => $state,
        ]);
    }

    public function fetchIdToken(string $code, string $redirectUri): string
    {
        try {
            $token = $this->provider($redirectUri)->getAccessToken('authorization_code', [
                'code' => $code,
            ]);
        } catch (IdentityProviderException $exception) {
            throw new GoogleSignInException('Google did not accept the authorization code.', previous: $exception);
        }

        $values = $token->getValues();
        $idToken = $values['id_token'] ?? null;

        if (!is_string($idToken) || '' === $idToken) {
            throw new GoogleSignInException('Google did not return an ID token.');
        }

        return $idToken;
    }

    private function provider(string $redirectUri): Google
    {
        if (!$this->isConfigured()) {
            throw new GoogleSignInException('Google OAuth is not configured.');
        }

        return new Google([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $redirectUri,
        ]);
    }
}
