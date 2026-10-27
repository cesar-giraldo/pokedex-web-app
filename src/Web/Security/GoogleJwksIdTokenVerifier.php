<?php

declare(strict_types=1);

namespace App\Web\Security;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function get_object_vars;
use function is_array;
use function time;

final class GoogleJwksIdTokenVerifier implements GoogleIdTokenVerifier
{
    private const string CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_ID')]
        private readonly string $clientId,
    ) {
    }

    public function verify(string $idToken): GoogleIdentity
    {
        try {
            $decoded = JWT::decode($idToken, JWK::parseKeySet($this->keySet()));
        } catch (GoogleSignInException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new GoogleSignInException('The Google ID token could not be verified.', previous: $exception);
        }

        /** @var array<string, mixed> $claims */
        $claims = get_object_vars($decoded);

        return GoogleIdTokenClaims::identity($claims, $this->clientId, time());
    }

    /**
     * @return array<string, mixed>
     */
    private function keySet(): array
    {
        return $this->cache->get('google.oauth.jwks', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            try {
                $payload = $this->httpClient->request('GET', self::CERTS_URL)->toArray();
            } catch (Throwable $exception) {
                throw new GoogleSignInException('Google public keys are unavailable.', previous: $exception);
            }

            $keys = $payload['keys'] ?? null;

            if (!is_array($keys) || [] === $keys) {
                throw new GoogleSignInException('Google public keys are unavailable.');
            }

            return $payload;
        });
    }
}
