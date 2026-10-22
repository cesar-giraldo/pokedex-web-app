<?php

declare(strict_types=1);

namespace App\Tests\Web\Security;

use App\Web\Security\GoogleIdTokenClaims;
use App\Web\Security\GoogleSignInException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[CoversClass(GoogleIdTokenClaims::class)]
final class GoogleIdTokenClaimsTest extends TestCase
{
    private const int NOW = 1_700_000_000;

    public function testAnUnverifiedEmailIsRejected(): void
    {
        $claims = $this->claims();
        $claims['email_verified'] = false;

        $this->expectException(GoogleSignInException::class);

        GoogleIdTokenClaims::identity($claims, 'client-id', self::NOW);
    }

    public function testAStringVerifiedFlagIsRejected(): void
    {
        $claims = $this->claims();
        $claims['email_verified'] = 'true';

        $this->expectException(GoogleSignInException::class);

        GoogleIdTokenClaims::identity($claims, 'client-id', self::NOW);
    }

    public function testATokenForAnotherClientIsRejected(): void
    {
        $this->expectException(GoogleSignInException::class);

        GoogleIdTokenClaims::identity($this->claims(), 'another-client', self::NOW);
    }

    public function testAnExpiredTokenIsRejected(): void
    {
        $claims = $this->claims();
        $claims['exp'] = self::NOW;

        $this->expectException(GoogleSignInException::class);

        GoogleIdTokenClaims::identity($claims, 'client-id', self::NOW);
    }

    public function testAVerifiedTokenExposesTheAccountFields(): void
    {
        $identity = GoogleIdTokenClaims::identity($this->claims(), 'client-id', self::NOW);

        self::assertSame('google-sub', $identity->sub);
        self::assertSame('ada@example.com', $identity->email);
        self::assertSame('Ada', $identity->givenName);
        self::assertSame('Lovelace', $identity->familyName);
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(): array
    {
        return [
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-id',
            'exp' => self::NOW + 60,
            'email_verified' => true,
            'sub' => 'google-sub',
            'email' => 'Ada@Example.com',
            'given_name' => 'Ada',
            'family_name' => 'Lovelace',
        ];
    }
}
